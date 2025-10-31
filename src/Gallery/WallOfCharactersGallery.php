<?php
namespace App\Gallery;
require_once "AbstractGallery.php";

class WallOfCharactersGallery extends WallOfImagesGallery {
    

    protected function getGalleryUrl() {
        return 'wall_of_characters.php';
    }

    protected function getBaseQuery(): string
    {
        return "v_gallery_characters";
    }

    protected function getGalleryEntity(): string {
        return "wall_of_characters";
    }

    protected function getGalleryTitle(): string {
        return "Wall of Characters";
    }


}
