<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportStockData extends Command
{
    protected $signature = 'import:stock {--test : Run in test mode without inserting data}';
    protected $description = 'Import stock data from CSV file into tblProductData';

    public function handle()
    {
        // Путь к файлу CSV
        $csvPath = storage_path('app/stock.csv');
        if (!file_exists($csvPath)) {
            $this->error('CSV file not found');
            return 0;
        }

        // Открытие и чтение CSV файла
        $file = fopen($csvPath, 'r');

        // Чтение заголовка и преобразование кодировки
        $header = fgetcsv($file);
        $header = array_map(function($value) {
            return mb_convert_encoding($value, 'UTF-8', 'auto');
        }, $header);

        $processed = 0;
        $success = 0;
        $skipped = 0;
        $failedItems = [];

        while ($row = fgetcsv($file)) {
            // Проверка на совпадение количества столбцов
            if (count($header) !== count($row)) {
                $this->error("Skipping invalid row: " . json_encode($row));
                $skipped++;
                continue;
            }

            // Преобразование строки данных в нормальную кодировку
            $row = array_map(function($value) {
                return mb_convert_encoding($value, 'UTF-8', 'auto');
            }, $row);

            $data = array_combine($header, $row);
            $processed++;

            // Проверка наличия обязательных данных
            if (empty($data['Product Code']) || empty($data['Product Name']) || empty($data['Cost in GBP']) || empty($data['Discontinued'])) {
                $this->error("Skipping row due to missing required fields: " . json_encode($data));
                $skipped++;
                continue;
            }

            // Проверяем стоимость и наличие на складе
            if (!is_numeric($data['Cost in GBP']) || !is_numeric($data['Stock'])) {
                $this->error("Invalid data in row: " . json_encode($data));
                $skipped++;
                continue;
            }
            if ((int)$data['Cost in GBP'] < 5 && (int)$data['Stock'] < 10) {
                $skipped++;
                continue;
            }

            if ((float)$data['Cost in GBP'] > 1000) {
                $skipped++;
                continue;
            }

            // Заполняем поле dtmDiscontinued
            if (isset($data['Discontinued']) && strtolower($data['Discontinued']) === 'yes') {
                $data['dtmDiscontinued'] = Carbon::now()->toDateTimeString();
            } else {
                $data['dtmDiscontinued'] = null; // Установим null, если поле пустое
            }
            $data['stmTimestamp'] = Carbon::now()->toDateTimeString();

            // Заполняем пустое поле Discontinued значением "no"
            if (empty($data['Discontinued'])) {
                $data['Discontinued'] = 'no';
            }

            // Если тестовый режим, пропускаем вставку
            if ($this->option('test')) {
                $this->info("Processed (Test mode): " . json_encode($data));
                $success++;
                continue;
            }
            // Исправление некорректных значений
            $data['Product Name'] = preg_replace('/[“”]/', '', $data['Product Name']);
            $data['Product Code'] = preg_replace('/[”]/', '', $data['Product Code']);

            // Очистка всех данных
            $data = array_map(function($value) {
                return trim($value); // Удаление пробелов в начале и конце
            }, $data);

            // Вставка данных в таблицу
            DB::table('tblProductData')->insert([
                'strProductName' => $data['Product Name'],
                'strProductDesc' => $data['Product Description'],
                'strProductCode' => $data['Product Code'],
                'dtmAdded' => Carbon::now()->toDateTimeString(),
                'dtmDiscontinued' => $data['dtmDiscontinued'],
                'stmTimestamp' => $data['stmTimestamp'],
                'price' => $data['Cost in GBP'],
                'stockLevel' => $data['Stock'],
            ]);
            try {
                $success++;
            } catch (\Exception $e) {
                $failedItems[] = $data;
            }
        }

        fclose($file);

        // Отчет об импорте
        $this->info("Processed: $processed items");
        $this->info("Successfully imported: $success items");
        $this->info("Skipped: $skipped items");

        if (!empty($failedItems)) {
            $this->error('Failed to import the following items:');
            foreach ($failedItems as $item) {
                $this->error(json_encode($item));
            }
        }

        return 0;

    }
}
