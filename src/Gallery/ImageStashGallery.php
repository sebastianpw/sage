<?php
namespace App\Gallery;
require_once "AbstractGallery.php";

class ImageStashGallery extends AbstractGallery {

    protected function getFiltersFromRequest(): array {
        // No filters for stash (yet)
        return [];
    }

    protected function getFilterOptions(): array {
        return [];
    }

    protected function getWhereClause(): string {
        return ''; // no filters
    }

    protected function getBaseQuery(): string {
        return "image_stash"; // your SQL table
    }

    protected function getOrderBy(): string {
	    return "id DESC";
    }

    protected function renderItem(array $row): string {
        $file = htmlspecialchars($row['file_path']);
        $title = htmlspecialchars($row['title'] ?? '');
        $date = htmlspecialchars($row['created_at']);

        $html = '<div class="img-wrapper">';
        $html .= '<a class="venobox" data-gall="stash" href="' . $file . '" data-title="' . $title . '">';
        $html .= '<img src="' . $file . '" alt="">';
        $html .= '</a>';
        $html .= '<div class="caption">';
	$html .= $title . '<br><small>' . $date . '</small>';
	$html .= ' <button class="copy-btn" data-path="' . htmlspecialchars($row['file_path']) . '">Copy URL</button>';
        $html .= '</div></div>';

        return $html;
    }

    protected function getGalleryEntity(): string {
        return "stash";
    }

    protected function getGalleryTitle(): string {
        return "Image Stash";
    }

    protected function getToggleButtonLeft(): int {
        return 0; // no filters, so button starts left
    }

    protected function getCaptionFields(): array {
        return [];
    }


}


