<?php

namespace Core;

use PhpOffice\PhpSpreadsheet\IOFactory;

class Spreadsheet
{
    public static function ler($arquivo)
    {
        $spreadsheet =
            IOFactory::load($arquivo);

        $sheet =
            $spreadsheet->getActiveSheet();

        return $sheet->toArray();
    }
}