<?php

namespace App\Services\Imports;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * Caps which cells PhpSpreadsheet actually loads into memory, independent of
 * whatever dimensions the file itself declares. A real spreadsheet's "used
 * range" can be inflated far beyond its actual data by stray formatting (a
 * fill color or border accidentally applied to thousands of otherwise-empty
 * rows/columns) -- without this filter, PhpSpreadsheet allocates a cell
 * object for every cell in that declared range during load(), before any
 * post-load bounding logic ever gets a chance to run.
 */
class BoundedRangeReadFilter implements IReadFilter
{
    private int $maxRow;
    private int $maxColumnIndex;

    public function __construct(int $maxRow = 20000, string $maxColumn = 'BZ')
    {
        $this->maxRow = $maxRow;
        $this->maxColumnIndex = Coordinate::columnIndexFromString($maxColumn);
    }

    public function readCell($columnAddress, $row, $worksheetName = ''): bool
    {
        if ($row > $this->maxRow) {
            return false;
        }

        return Coordinate::columnIndexFromString($columnAddress) <= $this->maxColumnIndex;
    }
}
