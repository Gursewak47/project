<?php

namespace App\Traits;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait BulkImport
{
    private $mappings;

    private $defaultColumns;

    public $temporaryTable;

    private $dataToImport;

    private $databaseConnection = 'tenant';

    private $importableColumnNames;

    private $allSheetColumnsNames;

    private $allSheetColumnsNamesWithTablePrefix;

    private $allSheetColumnsNamesWithTablePrefixWithDoubleQuotes;

    private $importableColumnNamesWithoutPrefix;

    private $importableColumnNamesWithTablePrefix;

    private $uniqueByColumns;

    private $uniqueByColumnsWithDoubleQuotes;

    private $sqlStatements;

    private $addSequence;

    private $whereConditionForUpsert;

    private $sequenceId = 'id';

    private $columnTypesForMigration = [
        'string'     => 'text',
        'text'       => 'text',
        'dateTimeTz' => 'dateTimeTz',
        'dateTime'   => 'dateTime',
        'date'       => 'date',
        'jsonb'      => 'jsonb',
        'decimal'    => 'decimal',
        'double'     => 'decimal',
        'integer'    => 'integer',
        'boolean'    => 'boolean',
        'metadata'   => 'jsonb',
    ];

    private $castColumnsType = [
        'string'     => 'text',
        'text'       => 'text',
        'dateTimeTz' => 'timestamp',
        'dateTime'   => 'timestamp',
        'decimal'    => 'decimal',
        'double'     => 'decimal',
        'integer'    => 'integer',
        'jsonb'      => 'json',
        'date'       => 'date',
        'boolean'    => 'boolean',
        'metadata'   => 'jsonb',
    ];

    /**
     * This inserts the data with INSERT FROM SELECT and UPDATE FROM SELECT database approach.
     *
     *
     * @param mixed $builder
     * @param mixed $args
     * @return mixed
     * @throws \Exception
     * @throws \InvalidArgumentException
     */
    public function scopeBulkImport($builder, $args)
    {
        ini_set('memory_limit', '2048M');

        //load mappings in variable
        $this->mappings = collect($args['mappings'])
            ->map(function ($column) {
                if (is_null(@$column['is_importable'])) {
                    $column['is_importable'] = true;
                }

                return $column;
            });

        //Handle join columns
        $joinColumns = collect([]);
        foreach ($this->mappings->whereNotNull('joins') as $mapping) {
            foreach ($mapping['joins'] as $join) {
                if (is_array($join['add_select'])) {
                    foreach ($join['add_select'] as $addSelect) {
                        if (is_null(@$addSelect['is_importable'])) {
                            $addSelect['is_importable'] = true;
                        }

                        $addSelect['is_joined_column'] = true;

                        if ($this->mappings->where('expected_column_name', $addSelect['expected_column_name'])->count() === 0) {
                            $joinColumns->push($addSelect);
                        }
                    }
                }
            }
        }

        //Default columns
        $this->defaultColumns = collect($args['default_columns'])
            ->map(function ($column) {
                if (is_null(@$column['is_importable'])) {
                    $column['is_importable'] = true;
                }

                return $column;
            });

        //Make sure stage columns exist in mappings
        foreach ($this->mappings->whereNotNull('joins') as $mapping) {
            foreach ($mapping['joins'] as $join) {
                foreach ($join['on'] as $joinOn) {
                    if (
                        $this->mappings->where('expected_column_name', $joinOn['stage_column_to_join'])->count()          === 0
                        && $this->defaultColumns->where('expected_column_name', $joinOn['stage_column_to_join'])->count() === 0
                    ) {
                        throw new \Exception('Missing mapping for '.$joinOn['stage_column_to_join'], 1);
                    }
                }
            }
        }

        $this->mappings                        = $this->mappings->merge($joinColumns);
        $this->uniqueByColumns                 = collect($args['unique_by_columns']);
        $this->uniqueByColumnsWithDoubleQuotes = collect($args['unique_by_columns'])
            ->map(function ($record) {
                return "\"$record\"";
            });
        $this->addSequence             = @$args['add_sequence'];
        $this->whereConditionForUpsert = @$args['where_conditions_for_upsert'];

        if (@$args['sequence_id']) {
            $this->sequenceId = $args['sequence_id'];
        }

        if (@$args['sql_statements_before_upsert']) {
            $this->sqlStatements = collect($args['sql_statements_before_upsert']);
        }

        //Set data to import
        $this->dataToImport = $args['data_to_import'];

        //Make select columns
        $this->createTemporaryTableName();
        $this->makeListForImportableColumns();
        $this->makeListForAllColumns();

        //load data in temporary table
        $this->loadDataInTemporaryTable();

        //Load joins data
        $this->loadJoinsData();

        //execute sql statements
        $this->executeSqlStatementsBeforeUpsert();

        //create index
        $this->addIndexForUniqueColumns();

        //before import hook
        $this->beforeImportHook();

        //Default upsert as true
        if (is_null(@$args['update']) || @$args['update'] === true) {
            $this->updateDataFeed();
        }

        if (is_null(@$args['insert']) || @$args['insert'] === true) {
            $this->insertDataFeed();
        }

        //after import hook
        $this->afterImportHook();

        //Add id for unique ID column
        $this->addUniqueIdColumnAfterUpsert();

        return $this->temporaryTable;
    }

    private function createTemporaryTableName()
    {
        //create temporary table
        if (! is_null(@$this->dataToImport['table_name'])) {
            $this->temporaryTable = $this->dataToImport['table_name'];
        } else {
            $this->temporaryTable = strtolower('temporary_table_'.preg_replace('~[0-9]~', '', Str::random(12)));
        }
    }

    private function dropColumnFromTemporaryTable($column)
    {
        DB::connection($this->databaseConnection)->statement("ALTER TABLE $this->temporaryTable DROP COLUMN IF EXISTS $column");
    }

    private function addIncrements()
    {
        $this->dropColumnFromTemporaryTable($this->sequenceId);

        Schema::connection($this->databaseConnection)
            ->table($this->temporaryTable, function (Blueprint $table) {
                $table->bigIncrements($this->sequenceId);
            });
    }

    private function loadJson()
    {
        $json = $this->dataToImport['data'];

        //Handle single quotes
        $json = str_replace("'", "''", $json);

        $temp = env('CREATE_TEMPORARY_TABLE') ? 'TEMPORARY' : '';

        if (env('USE_POSTGRES_JSON_APPROACH_FOR_BULK_IMPORT', true)) {
            $fileName   = Str::random(20);
            $path       = Storage::disk('temp')->path($fileName);
            $url        = Storage::disk('temp')->url($fileName).'.json';
            $outputPath = $path.'.json';

            if (env('DATA_FEED_LOCAL_IP')) {
                $url = Str::replace(env('APP_URL'), env('DATA_FEED_LOCAL_IP'), $url);
            }

            //DUMP json in file
            Storage::disk('temp')->put($fileName, $json);

            //Create output file
            exec("cat $path | jq -cr '.[]' | sed 's/\\[tn]//g' | sed 's/\\\\/\\\\\\\\/g' > $outputPath");

            //Patch to fix slash issue
            // file_put_contents($outputPath, str_replace('\"', '\\\"', file_get_contents($outputPath)));

            try {
                DB::connection('tenant')->statement("CREATE $temp TABLE $this->temporaryTable (json_data jsonb)");

                DB::connection('tenant')->statement("COPY $this->temporaryTable FROM PROGRAM 'curl \"$url\"'");

                Storage::disk('temp')->delete($fileName);
                Storage::disk('temp')->delete($fileName.'.json');
            } catch (\Exception $e) {
                Storage::disk('temp')->delete($fileName);
                Storage::disk('temp')->delete($fileName.'.json');
                throw $e;
            }
        } else {
            //Add with clause
            $queryWithClause = "WITH data_to_import AS (
                                    SELECT
                                        jsonb_array_elements('$json') AS json_data
                                ) SELECT * FROM data_to_import";

            DB::connection('tenant')->statement("CREATE $temp TABLE $this->temporaryTable AS $queryWithClause");
        }

        //Add ID integer
        $this->addIncrements();
    }

    private function loadQuery()
    {
        $temp = env('CREATE_TEMPORARY_TABLE') ? 'TEMPORARY' : '';
        DB::connection($this->databaseConnection)->statement("CREATE $temp TABLE $this->temporaryTable AS ".$this->dataToImport['query']->toSqlWithBindings());
        $this->addIncrements();
    }

    private function loadRawQuery()
    {
        $temp = env('CREATE_TEMPORARY_TABLE') ? 'TEMPORARY' : '';
        DB::connection($this->databaseConnection)->statement("CREATE $temp TABLE $this->temporaryTable AS ".$this->dataToImport['query']);
        $this->addIncrements();
    }

    //TEMP CODE
    private function loadCsv()
    {
        $file    = fopen($this->dataToImport['file'], 'r');
        $csv     = [];
        $headers = fgetcsv($file);

        foreach ($headers as $key => $header) {
            $headers[$key] = preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $header);
        }

        while (($result = fgetcsv($file)) !== false) {
            $_result = [];
            foreach ($headers as $key => $header) {
                $_result[$header] = $result[$key];
            }
            $csv[] = $_result;
        }
        $this->dataToImport['data'] = json_encode($csv);
        $this->dataToImport['type'] = 'json';

        $this->loadDataInTemporaryTable();
    }

    private function createMappings()
    {
        $this->mappings->each(function ($mapping) {
            if (@$mapping['data_column_name'] && ! str_contains($mapping['data_column_name'], '*')) {
                $this->dropColumnFromTemporaryTable($mapping['expected_column_name']);

                Schema::connection($this->databaseConnection)
                    ->table($this->temporaryTable, function (Blueprint $table) use ($mapping) {
                        $columnType = $this->columnTypesForMigration[$mapping['expected_column_type']];
                        if ($columnType === 'decimal') {
                            $table->{$columnType}($mapping['expected_column_name'], 24, 8)->nullable();
                        } else {
                            $table->{$columnType}($mapping['expected_column_name'])->nullable();
                        }
                    });
            }
        });
    }

    private function populateColumns()
    {
        $collectSelectClause = [];

        foreach ($this->mappings as $mapping) {
            if (@$mapping['data_column_name'] && ! str_contains($mapping['data_column_name'], '*')) {
                $updateValue = null;

                if (@$mapping['expected_column_type'] === 'metadata') {
                    $updateValue = 'json_data::json';
                } else {
                    $selectClauseExtractedFromJson = $this->getSelectSqlForJsonData('json_data', $mapping['data_column_name']);
                    $castJsonColumnTo              = $this->castColumnsType[$mapping['expected_column_type']];

                    $updateValue = "$selectClauseExtractedFromJson";

                    //This is used to remove the comma, percent etc.
                    switch ($castJsonColumnTo) {
                        case 'integer':
                            $updateValue = "regexp_replace(NULLIF($updateValue, ''), '[$,%]', '', 'gi')";
                            $updateValue = "($updateValue)::$castJsonColumnTo";
                            break;
                        case 'decimal':
                            $updateValue = "REPLACE(REPLACE(regexp_replace(NULLIF($updateValue, ''), '[$,%]', '', 'gi'), ',', ''), '%', '')";
                            $updateValue = "NULLIF($updateValue, '')";
                            $updateValue = "($updateValue)::$castJsonColumnTo";
                            break;

                        case 'timestamp':
                            if (@$mapping['date_format']) {
                                $dateFormat  = $mapping['date_format'];
                                $updateValue = "to_date(($updateValue), '$dateFormat')";

                                if (@$mapping['timezone']) {
                                    $updateValue = "($updateValue)::timestamp at time zone '".$mapping['timezone']."'";
                                }
                            } elseif (@$mapping['timezone']) {
                                $updateValue = "($updateValue)::timestamp at time zone 'utc' at time zone '".$mapping['timezone']."'";
                            } else {
                                $updateValue = "(CASE WHEN NULLIF($updateValue, '') IS NOT NULL THEN ($updateValue)::$castJsonColumnTo ELSE NULL END)";
                            }
                            break;

                        case 'date':
                            $updateValue = "to_date(($updateValue), 'YYYY-MM-DD')";
                            break;
                        default:
                            $updateValue = "($updateValue)::$castJsonColumnTo";
                            break;
                    }
                }

                $collectSelectClause[$mapping['expected_column_name']] = DB::raw($updateValue);
            }
        }

        if (count($collectSelectClause)) {
            DB::connection($this->databaseConnection)->table($this->temporaryTable)->update($collectSelectClause);
        }
    }

    private function recreateTableFromQuery($query)
    {
        $temp = env('CREATE_TEMPORARY_TABLE') ? 'TEMPORARY' : '';

        //Handle multiple queries
        $tempTable = strtolower('temporary_table_'.preg_replace('~[0-9]~', '', Str::random(20)));
        DB::connection($this->databaseConnection)->statement("CREATE $temp TABLE $tempTable AS ".$query);
        DB::connection($this->databaseConnection)->statement("DROP TABLE $this->temporaryTable");
        DB::connection($this->databaseConnection)->statement("CREATE $temp TABLE $this->temporaryTable AS SELECT * FROM $tempTable");
    }

    private function loadMultiDimensionalColumns()
    {
        $select = collect();
        $select->push($this->temporaryTable.'.*');
        foreach ($this->mappings as $mapping) {
            if (@$mapping['data_column_name'] && str_contains($mapping['data_column_name'], '*')) {
                $selectClause = $this->getSelectSqlForJsonData('json_data', $mapping['data_column_name']);
                $alias        = $mapping['expected_column_name'];
                $type         = $this->castColumnsType[$mapping['expected_column_type']];

                $this->dropColumnFromTemporaryTable($alias);

                $select->push("($selectClause)::$type AS $alias");
            }
        }

        if (count($select) > 1) {
            $this->recreateTableFromQuery('SELECT '.$select->implode(',').' from '.$this->temporaryTable);
        }
    }

    private function loadDefaultColumns()
    {
        $this->defaultColumns->each(function ($column) {
            $this->dropColumnFromTemporaryTable($column['expected_column_name']);

            Schema::connection($this->databaseConnection)
                ->table($this->temporaryTable, function (Blueprint $table) use ($column) {
                    if (! is_null($column['default_value'])) {
                        $table->{$column['expected_column_type']}($column['expected_column_name'])->default($column['default_value']);
                    } else {
                        $table->{$column['expected_column_type']}($column['expected_column_name'])->nullable();
                    }
                });
        });
    }

    private function loadJoinsData()
    {
        $query             = DB::connection($this->databaseConnection)->table($this->temporaryTable);
        $importableColumns = (clone $this->allSheetColumnsNamesWithTablePrefixWithDoubleQuotes);
        $hasJoins          = false;

        //Add joins
        foreach ($this->mappings->where('joins', '!=', null) as $mapping) {
            $temporaryColumnName = @$mapping['expected_column_name'];
            $hasJoins            = true;

            foreach ($mapping['joins'] as $join) {
                if (is_null(@$join['query'])) {
                    $query->{$join['type']}($join['table'], function ($query) use ($join) {
                        foreach ($join['on'] as $on) {
                            $query->on("$this->temporaryTable.".$on['stage_column_to_join'], '=', $join['table'].'.'.$on['database_column']);
                        }
                    });
                } else {
                    $query->{$join['type']}($join['query'], $join['table'], function ($query) use ($join) {
                        foreach ($join['on'] as $on) {
                            $query->on("$this->temporaryTable.".$on['stage_column_to_join'], '=', $join['table'].'.'.$on['database_column']);
                        }
                    });
                }

                $table = $join['table'];

                if (is_array($join['add_select'])) {
                    foreach ($join['add_select'] as $addSelect) {
                        $key                     = $importableColumns->search("\"$this->temporaryTable\".".'"'.$addSelect['expected_column_name'].'"');
                        $columnToSelectFromTable = $addSelect['data_column_name'];
                        $alias                   = $addSelect['expected_column_name'];
                        $importableColumns[$key] = "$table.\"$columnToSelectFromTable\" as $alias";
                    }
                } else {
                    $key                     = $importableColumns->search("$this->temporaryTable.$temporaryColumnName");
                    $columnToSelectFromTable = $join['add_select'];
                    $alias                   = $temporaryColumnName;
                    $importableColumns[$key] = "$table.$columnToSelectFromTable::integer as $alias";
                }
            }
        }

        if ($hasJoins) {
            //Add select
            $selectRaw = $importableColumns->implode(',');

            $query->selectRaw("$selectRaw,$this->temporaryTable.id,json_data");

            //Handle multiple queries
            $this->recreateTableFromQuery($query->toSqlWithBindings());
        }
    }

    private function loadDataInTemporaryTable()
    {
        switch ($this->dataToImport['type']) {
            case 'json':
                $this->loadJson();
                $this->createMappings();
                $this->populateColumns();
                $this->loadMultiDimensionalColumns();
                $this->loadDefaultColumns();
                break;

            case 'table':
                $this->createMappings();
                $this->populateColumns();
                $this->loadMultiDimensionalColumns();
                $this->loadDefaultColumns();
                break;

            case 'db_query':
                $this->loadQuery();
                $this->loadDefaultColumns();
                break;

            case 'raw_query':
                $this->loadRawQuery();
                $this->loadDefaultColumns();
                break;

            case 'csv':
                $this->loadCsv();
                break;
        }
    }

    public function scopeBeforeImportHook($builder)
    {
    }

    public function scopeAfterImportHook($builder)
    {
    }

    public function makeListForImportableColumns()
    {
        //Start with basic
        $this->importableColumnNames = $this->mappings->where('is_importable', true)->pluck('expected_column_name')->filter();

        //Default columns
        $this->importableColumnNames = $this->importableColumnNames->merge($this->defaultColumns->where('is_importable', true)->pluck('expected_column_name'));

        //Add prefix for table name
        $this->importableColumnNamesWithTablePrefix = (clone $this->importableColumnNames)->map(function ($column) {
            return $this->temporaryTable.'.'."\"$column\"";
        });
    }

    public function makeListForAllColumns()
    {
        //Start with basic
        $this->allSheetColumnsNames = $this->mappings->pluck('expected_column_name')->filter();

        //Default columns
        $this->allSheetColumnsNames = $this->allSheetColumnsNames->merge($this->defaultColumns->pluck('expected_column_name'));

        //Add prefix for table name
        $this->allSheetColumnsNamesWithTablePrefix = (clone $this->allSheetColumnsNames)->map(function ($column) {
            return $this->temporaryTable.'.'.$column;
        });

        $this->allSheetColumnsNamesWithTablePrefixWithDoubleQuotes = (clone $this->allSheetColumnsNames)->map(function ($column) {
            return "\"$this->temporaryTable\"".'.'."\"$column\"";
        });
    }

    public function scopeMakeSelectQueryForDataFeed($builder)
    {
        //Add joins
        foreach ($this->mappings->where('joins', '!=', null) as $mapping) {
            $temporaryColumnName = $mapping['expected_column_name'];

            foreach ($mapping['joins'] as $join) {
                $builder->{$join['type']}($join['table'], function ($query) use ($join, $temporaryColumnName) {
                    foreach ($join['on'] as $on) {
                        $query->on("$this->temporaryTable.$temporaryColumnName", '=', $join['table'].'.'.$on['database_column']);
                    }
                });
            }
        }

        //Add select
        $builder->select($this->importableColumnNamesWithTablePrefix->toArray());
    }

    public function scopeAddDistinctClause($builder)
    {
        if ($this->uniqueByColumns && $this->uniqueByColumn->count() > 0) {
            $builder->addSelect(DB::raw('DISTINCT ON ('.$this->uniqueByColumns->implode(',').')'));
            // $builder->distinct($this->uniqueByColumns->toArray());
        }

        return $builder;
    }

    public function scopeBulkImportFilter($builder, $temporaryTableName, $whereClause)
    {
        $className = get_class(clone $this);

        $newObject = new $className();

        if ($this->uniqueByColumns->count() > 0) {
            $builder->{$whereClause}(function ($query) use ($temporaryTableName, $newObject) {
                $query->selectRaw('NULL')
                    ->from($newObject->getTable())
                    ->where(function ($query) use ($temporaryTableName, $newObject) {
                        foreach ($this->uniqueByColumnsWithDoubleQuotes as $uniqueBy) {
                            $query->whereRaw($newObject->getTable().'.'."$uniqueBy"." = $temporaryTableName.$uniqueBy");
                        }
                    });
            });
        }

        return $builder->addWhereConditionWhileImporting();
    }

    public function scopeAddWhereConditionWhileImporting($builder)
    {
        foreach ($this->uniqueByColumnsWithDoubleQuotes as $uniqueColumn) {
            $builder->whereNotNull(DB::raw($uniqueColumn));
        }

        return $builder;
    }

    public function scopeAddSelectClause($builder)
    {
        $selectColumns = clone $this->importableColumnNamesWithTablePrefix;

        $distinct = '';

        foreach ($this->uniqueByColumnsWithDoubleQuotes as $uniqueByColumn) {
            if (! $selectColumns->contains($this->temporaryTable.'.'.$uniqueByColumn)) {
                $selectColumns->push($this->temporaryTable.'.'.$uniqueByColumn);
            }
        }

        if ($this->uniqueByColumnsWithDoubleQuotes && $this->uniqueByColumnsWithDoubleQuotes->count() > 0) {
            $distinct = 'DISTINCT ON ('.$this->uniqueByColumnsWithDoubleQuotes->implode(',').')';
        }

        $builder->select(DB::raw($distinct.$this->temporaryTable.'.'.$this->sequenceId.','.$selectColumns->implode(',')));

        return $builder;
    }

    public function getQueryForUpsert($clauseForOperation)
    {
        $subQuery = (clone $this)
            ->setTable($this->temporaryTable)
            ->addSelectClause()
            ->bulkImportFilter($this->temporaryTable, $clauseForOperation);

        if ($this->whereConditionForUpsert) {
            foreach ($this->whereConditionForUpsert as $whereCondition) {
                $subQuery = $subQuery->whereRaw($whereCondition);
            }
        }

        $selectColumns = (clone $this->importableColumnNames)->map(function ($record) {
            return "\"$record\"";
        });

        if ($clauseForOperation === 'whereExists') {
            foreach ($this->uniqueByColumnsWithDoubleQuotes as $uniqueByColumn) {
                if (! $selectColumns->contains($uniqueByColumn)) {
                    $selectColumns->push($uniqueByColumn);
                }
            }
        }

        $mainQuery = DB::table(DB::raw("({$subQuery->toSqlWithBindings()}) as sub"))->select(DB::raw($selectColumns->implode(',')))->orderBy('id');

        return $mainQuery;
    }

    private function insertDataFeed()
    {
        $queryForInsert = $this->getQueryForUpsert('whereNotExists');

        // Its a possibility that multiple API calls trying to update same records ex. Forward / Backward,
        // so we retry this 2 times
        retry(2, fn () => DB::connection($this->databaseConnection)
            ->table($this->getTable())
            ->insertUsing(
                $this->importableColumnNames->toArray(),
                $queryForInsert
            ), 1000);
    }

    private function updateDataFeed()
    {
        $queryForUpdate = $this->getQueryForUpsert('whereExists')->toSqlWithBindings();

        $makeSelectForUpdate = (clone $this->importableColumnNames)->map(function ($select) {
            return "\"$select\" = stage.\"$select\"";
        })
            ->implode(',');

        $whereConditionForUpdate = (clone $this->uniqueByColumnsWithDoubleQuotes)->map(function ($uniqueColumn) {
            return $this->getTable().'.'.$uniqueColumn." = stage.$uniqueColumn";
        })
            ->implode(' AND ');

        retry(
            2,
            fn () => DB::connection($this->databaseConnection)->statement('UPDATE '.$this->getTable()." SET $makeSelectForUpdate FROM ($queryForUpdate) as stage WHERE $whereConditionForUpdate"),
            1000
        );
    }

    public function addIndexForUniqueColumns()
    {
        if ($this->temporaryTable) {
            if ($this->uniqueByColumns->count() > 0) {
                Schema::connection('tenant')
                    ->table($this->temporaryTable, function ($table) {
                        foreach ($this->uniqueByColumns as $uniqueByColumn) {
                            $table->index([$uniqueByColumn], Str::random(30));
                        }

                        $table->index($this->uniqueByColumns->toArray(), Str::random(30));
                    });
            }
        }
    }

    public function executeSqlStatementsBeforeUpsert()
    {
        if ($this->sqlStatements) {
            foreach ($this->sqlStatements as $sql) {
                $sql['statement'] = str_replace('{temporaryTable}', "$this->temporaryTable", $sql['statement']);
                DB::connection($this->databaseConnection)->statement($sql['statement']);
            }
        }
    }

    private function getSelectSqlForJsonData($jsonColumnName, $extractColumn)
    {
        if (strpos($extractColumn, '*')) {
            $columns = explode('.', $extractColumn);
        } else {
            if (! strpos($extractColumn, '.->.')) {
                $columns = [$extractColumn];
            } else {
                $extractColumn = str_replace('.->.', '.', $extractColumn);
                $columns       = explode('.', $extractColumn);
            }
        }

        $count  = 0;
        $select = $jsonColumnName.' ';

        while ($count !== count($columns)) {
            if ($columns[$count] != '*') {
                if ($count === count($columns) - 1) {
                    $select = $select."->> '".$columns[$count]."'";
                } else {
                    $select = $select."-> '".$columns[$count]."'";
                }
            } else {
                $select = "jsonb_array_elements(($select)::jsonb)";
            }
            $count++;
        }

        return $select;
    }

    private function addUniqueIdColumnAfterUpsert()
    {
        // dd('Pending addUniqueIdColumnAfterUpsert');
    }
}
