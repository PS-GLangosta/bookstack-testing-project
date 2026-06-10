<?php

namespace Tests\Unit;

use BookStack\Sorting\BookSortMap;
use BookStack\Sorting\BookSortMapItem;
use PHPUnit\Framework\TestCase;

class BookSortMapTest extends TestCase
{
    public function test_new_map_has_no_items(): void
    {
        $map = new BookSortMap();
        $this->assertSame([], $map->all());
    }

    public function test_add_item_stores_and_retrieves_it(): void
    {
        $map = new BookSortMap();
        $item = new BookSortMapItem(1, 0, null, 'page', 10);
        $map->addItem($item);

        $all = $map->all();
        $this->assertCount(1, $all);
        $this->assertSame($item, $all[0]);
    }

    public function test_multiple_items_preserve_insertion_order(): void
    {
        $map = new BookSortMap();
        $item1 = new BookSortMapItem(1, 0, null, 'page', 10);
        $item2 = new BookSortMapItem(2, 1, 5, 'chapter', 10);
        $map->addItem($item1);
        $map->addItem($item2);

        $all = $map->all();
        $this->assertCount(2, $all);
        $this->assertSame(1, $all[0]->id);
        $this->assertSame(2, $all[1]->id);
    }

    public function test_from_json_parses_page_item_correctly(): void
    {
        $json = json_encode([
            ['id' => 5, 'sort' => 2, 'parentChapter' => null, 'type' => 'page', 'book' => 10],
        ]);

        $map = BookSortMap::fromJson($json);
        $items = $map->all();

        $this->assertCount(1, $items);
        $this->assertSame(5, $items[0]->id);
        $this->assertSame(2, $items[0]->sort);
        $this->assertNull($items[0]->parentChapterId);
        $this->assertSame('page', $items[0]->type);
        $this->assertSame(10, $items[0]->parentBookId);
    }

    public function test_from_json_parses_non_null_parent_chapter(): void
    {
        $json = json_encode([
            ['id' => 3, 'sort' => 1, 'parentChapter' => 7, 'type' => 'page', 'book' => 10],
        ]);

        $map = BookSortMap::fromJson($json);
        $this->assertSame(7, $map->all()[0]->parentChapterId);
    }

    public function test_from_json_parses_multiple_items(): void
    {
        $json = json_encode([
            ['id' => 1, 'sort' => 0, 'parentChapter' => null, 'type' => 'page', 'book' => 5],
            ['id' => 2, 'sort' => 1, 'parentChapter' => null, 'type' => 'chapter', 'book' => 5],
            ['id' => 3, 'sort' => 0, 'parentChapter' => 2, 'type' => 'page', 'book' => 5],
        ]);

        $map = BookSortMap::fromJson($json);
        $this->assertCount(3, $map->all());
    }

    public function test_from_json_converts_string_values_to_int(): void
    {
        $json = json_encode([
            ['id' => '5', 'sort' => '2', 'parentChapter' => '7', 'type' => 'page', 'book' => '10'],
        ]);

        $map = BookSortMap::fromJson($json);
        $item = $map->all()[0];

        $this->assertIsInt($item->id);
        $this->assertIsInt($item->sort);
        $this->assertIsInt($item->parentChapterId);
        $this->assertIsInt($item->parentBookId);
    }

    public function test_sort_map_item_stores_all_properties(): void
    {
        $item = new BookSortMapItem(42, 3, 5, 'chapter', 99);

        $this->assertSame(42, $item->id);
        $this->assertSame(3, $item->sort);
        $this->assertSame(5, $item->parentChapterId);
        $this->assertSame('chapter', $item->type);
        $this->assertSame(99, $item->parentBookId);
    }

    public function test_sort_map_item_accepts_null_parent_chapter(): void
    {
        $item = new BookSortMapItem(1, 0, null, 'page', 5);
        $this->assertNull($item->parentChapterId);
    }

    public function test_sort_map_item_type_can_be_page_or_chapter(): void
    {
        $page = new BookSortMapItem(1, 0, null, 'page', 5);
        $chapter = new BookSortMapItem(2, 0, null, 'chapter', 5);

        $this->assertSame('page', $page->type);
        $this->assertSame('chapter', $chapter->type);
    }
}
