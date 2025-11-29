<?php
namespace App\Gallery;
require_once "AbstractGallery.php";

class WallOfLocationsGallery extends WallOfImagesGallery {
    

    protected function getGalleryUrl(): string {
        return 'wall_of_locations.php';
    }

    protected function getBaseQuery(): string
    {
        return "v_gallery_locations";
    }

    protected function getGalleryEntity(): string {
        return "wall_of_locations";
    }

    protected function getGalleryTitle(): string {
        return "Wall of Locations";
    }


}
