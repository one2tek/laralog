<?php

return [
    
    /*
     * This is the name of the table that will be created by the migration and
     * used by the Log model shipped with this package.
     */
    'table_name' => 'logs',

    /*
     * This is the database connection that will be used by the migration and
     * the LaraLog model shipped with this package.
     */
    'database_connection' => 'mysql',


    /*
     * If you want to disable logs in specific environments this is
     * the exactly place.
     */
    'disable_on_environments' => ['local'],
];
