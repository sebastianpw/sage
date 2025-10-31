<?php
namespace App\Gallery;
require_once "AbstractGallery.php";

class WallOfAnimasGallery extends WallOfImagesGallery {
    

    protected function getGalleryUrl() {
        return 'wall_of_animas.php';
    }

    protected function getBaseQuery(): string
    {
        return "v_gallery_animas";
    }

    protected function getGalleryEntity(): string {
        return "wall_of_animas";
    }

    protected function getGalleryTitle(): string {
        return "Wall of Animas";
    }


}
