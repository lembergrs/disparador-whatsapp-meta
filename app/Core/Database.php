<?php

namespace Core;

use PDO;
use PDOException;

class Database
{
    private static $instance;

    public static function getInstance()
    {
        if(!self::$instance){

            try{

                self::$instance = new PDO(
                    "mysql:host=".DB_HOST.";
                    dbname=".DB_NAME.";
                    charset=utf8mb4",
                    DB_USER,
                    DB_PASS
                );

                self::$instance->setAttribute(
                    PDO::ATTR_ERRMODE,
                    PDO::ERRMODE_EXCEPTION
                );

            }catch(PDOException $e){

                die($e->getMessage());

            }

        }

        return self::$instance;
    }
}