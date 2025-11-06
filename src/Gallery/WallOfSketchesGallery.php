<?php
namespace App\Gallery;
require_once "AbstractGallery.php";

class WallOfSketchesGallery extends WallOfImagesGallery {
    

    protected function getGalleryUrl() {
        return 'wall_of_sketches.php';
    }

    protected function getBaseQuery(): string
    {
        return "v_gallery_sketches";
    }

    protected function getGalleryEntity(): string {
        return "wall_of_sketches";
    }

    protected function getGalleryTitle(): string {
        return "Wall of Sketches";
    }


}
