<?php
namespace App\Gallery;
require_once "AbstractGallery.php";

class WallOfBackgroundsGallery extends WallOfImagesGallery {
    

    protected function getGalleryUrl() {
        return 'wall_of_backgrounds.php';
    }

    protected function getBaseQuery(): string
    {
        return "v_gallery_backgrounds";
    }

    protected function getGalleryEntity(): string {
        return "wall_of_backgrounds";
    }

    protected function getGalleryTitle(): string {
        return "Wall of Backgrounds";
    }


}
