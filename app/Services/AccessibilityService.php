<?php

namespace App\Services;

class AccessibilityService
{
    /**
     * Generate WCAG 2.1 AA compliant HTML structure
     */
    public function generateAccessibleTable($data, $headers, $tableId = null, $caption = null)
    {
        $tableId = $tableId ?: 'accessible-table-' . uniqid();
        
        $html = '<table id="' . $tableId . '" class="table table-striped" role="table" aria-label="' . ($caption ?: 'Data table') . '">';
        
        if ($caption) {
            $html .= '<caption class="sr-only">' . $caption . '</caption>';
        }
        
        // Headers with proper scope
        $html .= '<thead><tr role="row">';
        foreach ($headers as $index => $header) {
            $scope = is_array($header) ? ($header['scope'] ?? 'col') : 'col';
            $label = is_array($header) ? $header['label'] : $header;
            $id = is_array($header) ? ($header['id'] ?? "header-{$index}") : "header-{$index}";
            
            $html .= '<th role="columnheader" scope="' . $scope . '" id="' . $id . '" tabindex="0">' . 
                     htmlspecialchars($label) . '</th>';
        }
        $html .= '</tr></thead>';
        
        // Body with row headers
        $html .= '<tbody>';
        foreach ($data as $rowIndex => $row) {
            $html .= '<tr role="row">';
            foreach ($row as $cellIndex => $cell) {
                $cellId = "cell-{$rowIndex}-{$cellIndex}";
                $headers = "header-{$cellIndex}";
                
                if ($cellIndex === 0) {
                    // First column as row header
                    $html .= '<th role="rowheader" id="' . $cellId . '" scope="row" tabindex="0">' . 
                             htmlspecialchars($cell) . '</th>';
                } else {
                    $html .= '<td role="cell" headers="' . $headers . '" id="' . $cellId . '" tabindex="0">' . 
                             htmlspecialchars($cell) . '</td>';
                }
            }
            $html .= '</tr>';
        }
        $html .= '</tbody>';
        
        $html .= '</table>';
        
        return $html;
    }
    
    /**
     * Generate accessible form with proper labels and ARIA attributes
     */
    public function generateAccessibleForm($fields, $formId = null, $submitText = 'Submit')
    {
        $formId = $formId ?: 'accessible-form-' . uniqid();
        
        $html = '<form id="' . $formId . '" role="form" novalidate>';
        
        foreach ($fields as $field) {
            $fieldId = $field['id'] ?? 'field-' . uniqid();
            $label = $field['label'] ?? '';
            $type = $field['type'] ?? 'text';
            $required = $field['required'] ?? false;
            $helpText = $field['help'] ?? '';
            $placeholder = $field['placeholder'] ?? '';
            
            $html .= '<div class="mb-3">';
            
            if ($label) {
                $html .= '<label for="' . $fieldId . '" class="form-label">' . 
                         htmlspecialchars($label) . 
                         ($required ? ' <span class="text-danger" aria-label="required">*</span>' : '') . 
                         '</label>';
            }
            
            $inputAttributes = [
                'id' => $fieldId,
                'class' => 'form-control',
                'aria-describedby' => $helpText ? $fieldId . '-help' : '',
                'required' => $required ? 'required' : '',
                'placeholder' => $placeholder
            ];
            
            switch ($type) {
                case 'textarea':
                    $html .= '<textarea ' . $this->buildAttributes($inputAttributes) . '></textarea>';
                    break;
                    
                case 'select':
                    $html .= '<select ' . $this->buildAttributes($inputAttributes) . '>';
                    if (isset($field['options'])) {
                        foreach ($field['options'] as $option) {
                            $value = is_array($option) ? $option['value'] : $option;
                            $text = is_array($option) ? $option['text'] : $option;
                            $selected = isset($option['selected']) && $option['selected'] ? 'selected' : '';
                            $html .= '<option value="' . htmlspecialchars($value) . '" ' . $selected . '>' . 
                                     htmlspecialchars($text) . '</option>';
                        }
                    }
                    $html .= '</select>';
                    break;
                    
                case 'checkbox':
                    $html .= '<div class="form-check">';
                    $inputAttributes['class'] = 'form-check-input';
                    $inputAttributes['type'] = 'checkbox';
                    $html .= '<input ' . $this->buildAttributes($inputAttributes) . '>';
                    if ($label) {
                        $html .= '<label class="form-check-label" for="' . $fieldId . '">' . 
                                 htmlspecialchars($label) . '</label>';
                    }
                    $html .= '</div>';
                    break;
                    
                default:
                    $inputAttributes['type'] = $type;
                    $html .= '<input ' . $this->buildAttributes($inputAttributes) . '>';
            }
            
            if ($helpText) {
                $html .= '<div id="' . $fieldId . '-help" class="form-text">' . 
                         htmlspecialchars($helpText) . '</div>';
            }
            
            $html .= '</div>';
        }
        
        $html .= '<button type="submit" class="btn btn-primary" aria-label="Submit form">' . 
                 htmlspecialchars($submitText) . '</button>';
        $html .= '</form>';
        
        return $html;
    }
    
    /**
     * Generate accessible navigation
     */
    public function generateAccessibleNavigation($items, $navId = null, $ariaLabel = 'Main navigation')
    {
        $navId = $navId ?: 'nav-' . uniqid();
        
        $html = '<nav id="' . $navId . '" role="navigation" aria-label="' . $ariaLabel . '">';
        $html .= '<ul class="nav flex-column">';
        
        foreach ($items as $item) {
            $active = $item['active'] ?? false;
            $disabled = $item['disabled'] ?? false;
            $url = $item['url'] ?? '#';
            $text = $item['text'] ?? '';
            $icon = $item['icon'] ?? '';
            
            $classes = ['nav-link'];
            if ($active) $classes[] = 'active';
            if ($disabled) $classes[] = 'disabled';
            
            $html .= '<li class="nav-item">';
            $html .= '<a class="' . implode(' ', $classes) . '" href="' . $url . '"';
            
            if ($disabled) {
                $html .= ' aria-disabled="true" tabindex="-1"';
            } else {
                $html .= ' tabindex="0"';
            }
            
            $html .= '>';
            
            if ($icon) {
                $html .= '<i class="' . $icon . '" aria-hidden="true"></i> ';
            }
            
            $html .= htmlspecialchars($text);
            $html .= '</a>';
            $html .= '</li>';
        }
        
        $html .= '</ul>';
        $html .= '</nav>';
        
        return $html;
    }
    
    /**
     * Generate accessible alert messages
     */
    public function generateAccessibleAlert($message, $type = 'info', $dismissable = true)
    {
        $alertTypes = [
            'success' => 'alert-success',
            'info' => 'alert-info', 
            'warning' => 'alert-warning',
            'danger' => 'alert-danger'
        ];
        
        $ariaRoles = [
            'success' => 'status',
            'info' => 'status',
            'warning' => 'alert',
            'danger' => 'alert'
        ];
        
        $classes = ['alert', $alertTypes[$type] ?? 'alert-info', 'alert-dismissible', 'fade', 'show'];
        $role = $ariaRoles[$type] ?? 'status';
        $alertId = 'alert-' . uniqid();
        
        $html = '<div id="' . $alertId . '" class="' . implode(' ', $classes) . '" role="' . $role . '" aria-live="polite">';
        
        if ($dismissable) {
            $html .= '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close alert"></button>';
        }
        
        $html .= htmlspecialchars($message);
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Generate screen reader only text
     */
    public function screenReaderOnly($text)
    {
        return '<span class="sr-only">' . htmlspecialchars($text) . '</span>';
    }
    
    /**
     * Generate accessible progress indicator
     */
    public function generateAccessibleProgress($percentage, $label = 'Progress', $max = 100)
    {
        $progressId = 'progress-' . uniqid();
        
        $html = '<div class="mb-3">';
        $html .= '<label for="' . $progressId . '" class="form-label">' . htmlspecialchars($label) . '</label>';
        $html .= '<div class="progress" role="progressbar" aria-valuenow="' . $percentage . '" aria-valuemin="0" aria-valuemax="' . $max . '">';
        $html .= '<div class="progress-bar" style="width: ' . $percentage . '%">' . $percentage . '%</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Generate keyboard navigable data grid
     */
    public function generateDataGrid($data, $headers, $gridId = null)
    {
        $gridId = $gridId ?: 'data-grid-' . uniqid();
        
        $html = '<div id="' . $gridId . '" role="grid" class="table-responsive" tabindex="0">';
        $html .= '<table class="table table-bordered">';
        
        // Header row
        $html .= '<thead><tr role="row">';
        foreach ($headers as $index => $header) {
            $headerId = $gridId . '-header-' . $index;
            $html .= '<th role="columnheader" id="' . $headerId . '" scope="col" tabindex="-1">' . 
                     htmlspecialchars($header) . '</th>';
        }
        $html .= '</tr></thead>';
        
        // Data rows
        $html .= '<tbody>';
        foreach ($data as $rowIndex => $row) {
            $rowId = $gridId . '-row-' . $rowIndex;
            $html .= '<tr role="row" id="' . $rowId . '">';
            
            foreach ($row as $cellIndex => $cell) {
                $cellId = $gridId . '-cell-' . $rowIndex . '-' . $cellIndex;
                $headers = $gridId . '-header-' . $cellIndex;
                
                $html .= '<td role="gridcell" id="' . $cellId . '" headers="' . $headers . '" tabindex="-1">';
                $html .= htmlspecialchars($cell);
                $html .= '</td>';
            }
            
            $html .= '</tr>';
        }
        $html .= '</tbody>';
        
        $html .= '</table>';
        $html .= '</div>';
        
        // Add keyboard navigation JavaScript
        $html .= $this->getGridNavigationScript($gridId);
        
        return $html;
    }
    
    /**
     * Generate keyboard navigation script for grids
     */
    private function getGridNavigationScript($gridId)
    {
        return '
        <script>
        document.getElementById("' . $gridId . '").addEventListener("keydown", function(e) {
            const grid = e.target.closest("[role=grid]");
            const rows = grid.querySelectorAll("[role=row]:not(:first-child)");
            const currentRow = e.target.closest("[role=row]");
            const currentCell = e.target.closest("[role=gridcell], [role=columnheader]");
            
            if (!currentRow || !currentCell) return;
            
            const rowIndex = Array.from(rows).indexOf(currentRow);
            const cells = currentRow.querySelectorAll("[role=gridcell], [role=columnheader]");
            const cellIndex = Array.from(cells).indexOf(currentCell);
            
            let newRow, newCell;
            
            switch(e.key) {
                case "ArrowUp":
                    e.preventDefault();
                    newRow = rows[Math.max(0, rowIndex - 1)];
                    newCell = newRow?.querySelectorAll("[role=gridcell], [role=columnheader]")[cellIndex];
                    break;
                case "ArrowDown":
                    e.preventDefault();
                    newRow = rows[Math.min(rows.length - 1, rowIndex + 1)];
                    newCell = newRow?.querySelectorAll("[role=gridcell], [role=columnheader]")[cellIndex];
                    break;
                case "ArrowLeft":
                    e.preventDefault();
                    newCell = cells[Math.max(0, cellIndex - 1)];
                    break;
                case "ArrowRight":
                    e.preventDefault();
                    newCell = cells[Math.min(cells.length - 1, cellIndex + 1)];
                    break;
                case "Home":
                    e.preventDefault();
                    newCell = cells[0];
                    break;
                case "End":
                    e.preventDefault();
                    newCell = cells[cells.length - 1];
                    break;
                case "PageUp":
                    e.preventDefault();
                    newRow = rows[0];
                    newCell = newRow?.querySelectorAll("[role=gridcell], [role=columnheader]")[cellIndex];
                    break;
                case "PageDown":
                    e.preventDefault();
                    newRow = rows[rows.length - 1];
                    newCell = newRow?.querySelectorAll("[role=gridcell], [role=columnheader]")[cellIndex];
                    break;
            }
            
            if (newCell) {
                newCell.focus();
                newCell.tabIndex = 0;
                currentCell.tabIndex = -1;
            }
        });
        </script>
        ';
    }
    
    /**
     * Helper method to build HTML attributes
     */
    private function buildAttributes($attributes)
    {
        $attrStrings = [];
        foreach ($attributes as $key => $value) {
            if ($value !== '' && $value !== null) {
                $attrStrings[] = $key . '="' . htmlspecialchars($value) . '"';
            }
        }
        return implode(' ', $attrStrings);
    }
}