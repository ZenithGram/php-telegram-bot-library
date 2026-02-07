<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ZenithGram\ZenithGram\Pagination;
use ZenithGram\ZenithGram\Enums\PaginationMode;
use ZenithGram\ZenithGram\Enums\PaginationLayout;
use ZenithGram\ZenithGram\Enums\PaginationNumberStyle;

class PaginationTest extends TestCase
{
    /**
     * Хелпер для генерации тестовых кнопок
     */
    private function generateItems(int $count): array
    {
        $items = [];
        for ($i = 1; $i <= $count; $i++) {
            $items[] = ['text' => "Item $i", 'callback_data' => "btn_$i"];
        }

        return $items;
    }

    public function testTotalPageCalculation(): void
    {
        $p = new Pagination();

        $p->setItems($this->generateItems(10))->setPerPage(5);
        $this->assertEquals(2, $p->getTotalPage());

        $p->setItems($this->generateItems(11));
        $this->assertEquals(3, $p->getTotalPage());

        $p->setItems([]);
        $this->assertEquals(0, $p->getTotalPage());
    }

    /**
     * Проверяем, что на странице выводятся правильные элементы
     */
    public function testSlicingLogic(): void
    {
        $items = $this->generateItems(10);
        $p = new Pagination();
        $p
            ->setItems($items)
            ->setPerPage(2)
            ->setPage(2)
            ->setPrefix('p_');

        $result = $p->create();

        $this->assertEquals('Item 3', $result[0][0]['text']);
        $this->assertEquals('Item 4', $result[1][0]['text']);
    }

    /**
     * Проверяем разбиение на колонки
     */
    public function testColumns(): void
    {
        $p = new Pagination();
        $p
            ->setItems($this->generateItems(4))
            ->setPerPage(4)
            ->setColumns(2)
            ->setPrefix('p_');

        $keyboard = $p->create();

        $this->assertCount(2, $keyboard[0]);
        $this->assertEquals('Item 1', $keyboard[0][0]['text']);
        $this->assertEquals('Item 2', $keyboard[0][1]['text']);
    }

    /**
     * Тестируем логику стрелок (Arrows Mode)
     */
    public function testArrowNavigationVisibility(): void
    {
        $items = $this->generateItems(30);
        $p = new Pagination();
        $p->setItems($items)->setPerPage(10)->setPrefix('p_');

        $p->setPage(1);
        $kbd1 = $p->create();
        $navRow1 = end($kbd1);

        $this->assertCount(1, $navRow1);
        $this->assertEquals('>', $navRow1[0]['text']);

        $p->setPage(2);
        $kbd2 = $p->create();
        $navRow2 = end($kbd2);

        $this->assertCount(2, $navRow2);
        $this->assertEquals('<', $navRow2[0]['text']);
        $this->assertEquals('>', $navRow2[1]['text']);

        $p->setPage(3);
        $kbd3 = $p->create();
        $navRow3 = end($kbd3);

        $this->assertCount(1, $navRow3);
        $this->assertEquals('<', $navRow3[0]['text']);
    }

    /**
     * Тестируем числовой режим (Numbers Mode)
     */
    public function testNumbersMode(): void
    {
        $p = new Pagination();
        $p
            ->setItems($this->generateItems(50))
            ->setPerPage(10)
            ->setPage(3)
            ->setPrefix('page_')
            ->setMode(PaginationMode::NUMBERS)
            ->setActivePageFormat('- %s -');

        $kbd = $p->create();
        $navRow = end($kbd);

        $this->assertCount(5, $navRow);

        $this->assertEquals('page_1', $navRow[0]['callback_data']);

        $this->assertEquals('- 3 -', $navRow[2]['text']);
    }

    /**
     * Тестируем лейаут навигации (SPLIT)
     * Кнопки "В начало/В конец" должны быть отделены от "Назад/Вперед"
     */
    public function testNavigationLayoutSplit(): void
    {
        $p = new Pagination();
        $p
            ->setItems($this->generateItems(100))
            ->setPerPage(10)
            ->setPage(5) // Середина
            ->setPrefix('p_')
            ->setSideSigns('First', 'Last')
            ->setNavigationLayout(PaginationLayout::SPLIT);

        $kbd = $p->create();

        $lastRow = array_pop($kbd);
        $prevRow = array_pop($kbd);

        $this->assertCount(2, $lastRow);
        $this->assertEquals('First', $lastRow[0]['text']);

        $this->assertCount(2, $prevRow);
        $this->assertEquals('<', $prevRow[0]['text']);
    }

    /**
     * Тестируем дополнительные кнопки (Return Button)
     */
    public function testReturnButton(): void
    {
        $p = new Pagination();
        $p
            ->setItems($this->generateItems(5))
            ->setPerPage(5) // 1 страница
            ->setPrefix('p_')
            ->addReturnBtn('Back Menu', 'menu_cb');

        $kbd = $p->create();

        $lastRow = end($kbd);

        $this->assertEquals('Back Menu', $lastRow[0]['text']);
        $this->assertEquals('menu_cb', $lastRow[0]['callback_data']);
    }

    /**
     * Тестируем header buttons
     */
    public function testHeaderButtons(): void
    {
        $p = new Pagination();
        $header = [['text' => 'Top', 'callback_data' => 'top']];

        $p
            ->setItems($this->generateItems(1))
            ->setPrefix('p_')
            ->addHeaderBtn($header);

        $kbd = $p->create();

        $this->assertEquals($header, $kbd[0]);
    }

    /**
     * Проверяем, что класс не дает установить некорректные значения
     */
    public function testValidationExceptions(): void
    {
        $p = new Pagination();

        try {
            $p->setPerPage(0);
            $this->fail('Ожидалось исключение при PerPage=0');
        } catch (\LogicException $e) {
            $this->assertTrue(true);
        }

        try {
            $p->setPage(-1);
            $this->fail('Ожидалось исключение при Page=-1');
        } catch (\LogicException $e) {
            $this->assertTrue(true);
        }

        try {
            $p->setColumns(0);
            $this->fail('Ожидалось исключение при Columns=0');
        } catch (\LogicException $e) {
            $this->assertTrue(true);
        }

        try {
            $p->setPrefix('');
            $this->fail('Ожидалось исключение при пустом префиксе');
        } catch (\LogicException $e) {
            $this->assertTrue(true);
        }
    }

    public function testNumbersSlidingWindow(): void
    {
        $p = new Pagination();
        $p
            ->setItems($this->generateItems(100))
            ->setPerPage(10)
            ->setPrefix('p_')
            ->setMode(PaginationMode::NUMBERS)
            ->setMaxPageBtn(5);

        // 1. Начало
        $p->setPage(1);
        $res1 = $p->create();
        $row1 = end($res1);
        $this->assertEquals('1', $row1[0]['text']);
        $this->assertEquals('5', $row1[4]['text']);

        // 2. Середина
        $p->setPage(5);
        $res2 = $p->create();
        $row2 = end($res2);
        $this->assertEquals('3', $row2[0]['text']);
        $this->assertEquals('7', $row2[4]['text']);
        $this->assertEquals('5', $row2[2]['text']);

        // 3. Конец
        $p->setPage(10);
        $res3 = $p->create();
        $row3 = end($res3);
        $this->assertEquals('6', $row3[0]['text']);
        $this->assertEquals('10', $row3[4]['text']);
    }

    public function testMultiDigitEmoji(): void
    {
        $p = new Pagination();
        $p
            ->setItems($this->generateItems(100))
            ->setPerPage(10)
            ->setPage(10)
            ->setPrefix('p_')
            ->setMode(PaginationMode::NUMBERS)
            ->setNumberStyle(PaginationNumberStyle::EMOJI);

        $result = $p->create();
        $navRow = end($result);

        $lastBtn = end($navRow);

        // 1 -> 1️⃣, 0 -> 0️⃣.  10 -> 1️⃣0️⃣
        $this->assertEquals('1️⃣0️⃣', $lastBtn['text']);
    }

    public function testSmartLayout(): void
    {
        $p = new Pagination();
        $p
            ->setItems($this->generateItems(50))
            ->setPerPage(10)
            ->setPage(2)
            ->setPrefix('p_')
            ->setNavigationLayout(PaginationLayout::SMART);

        $kbd1 = $p->create();
        $navRow1 = end($kbd1);
        $this->assertCount(2, $navRow1);

        $p->setSideSigns('<<', '>>');
        $kbd2 = $p->create();

        $lastRow = array_pop($kbd2);
        $penultRow = array_pop($kbd2);

        $this->assertCount(2, $penultRow, 'Inner buttons count');
        $this->assertCount(2, $lastRow, 'Outer buttons count');
    }

    public function testActivePageFormatLeftRight(): void
    {
        $p = new Pagination();
        $p
            ->setItems($this->generateItems(30))
            ->setPerPage(10)
            ->setPage(1)
            ->setPrefix('p_')
            ->setMode(PaginationMode::NUMBERS)
            ->setActivePageFormat('>> ', ' <<');

        $result = $p->create();
        $navRow = end($result);

        $this->assertEquals('>> 1 <<', $navRow[0]['text']);
        $this->assertEquals('2', $navRow[1]['text']);
    }
}