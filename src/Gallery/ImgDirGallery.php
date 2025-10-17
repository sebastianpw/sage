<?php
namespace App\Gallery;
require_once "AbstractGallery.php";

class ImgDirGallery extends AbstractGallery {

    protected string $folder;
    protected ?array $validFolders = null; // if set, only these folders will be shown; if null or empty, show all

    public function __construct(string $folder, ?array $validFolders = null) {
        $this->folder = rtrim($folder, '/');
        $this->validFolders = $validFolders;
        parent::__construct(new mysqli()); // dummy mysqli just for parent compatibility
    }

    protected function getFiltersFromRequest(): array {
        return [];
    }

    protected function getFilterOptions(): array {
        return [];
    }

    protected function getWhereClause(): string {
        return '';
    }

    protected function getBaseQuery(): string {
        return '';
    }

    protected function getOrderBy(): string {
        return 'desc';
    }

    protected function fetchFiles(): array {
        $files = glob($this->folder . "/*.{jpg,jpeg,png,gif}", GLOB_BRACE);
        rsort($files); // newest first

        $publicFolder = realpath(\App\Core\SpwBase::getInstance()->getProjectPath() . '/public');

        $urls = array_map(function($file) use ($publicFolder) {
            $relative = str_replace($publicFolder, '', realpath($file));
            return '/' . ltrim($relative, '/');
        }, $files);

        return $urls;
    }

    protected function renderItem(array $row): string {
        $fields = $this->getCaptionFields();
        $filename = htmlspecialchars($row['filename']);
        $prompt   = htmlspecialchars($row['prompt'] ?? '');

        $captionParts = [];
        if (!empty($prompt)) {
            $captionParts[] = $prompt;
        }
        foreach ($fields as $label => $field) {
            if (!empty($row[$field])) {
                $captionParts[] = '<strong>' . htmlspecialchars($label) . ':</strong> ' . htmlspecialchars($row[$field]);
            }
        }

        $captionParts[] = '<em style="position: absolute; left:0; top:0; width: 150px; overflow: hidden;">' . $filename . '</em>';
        $captionHtml = implode('<br>', $captionParts);

        ob_start(); ?>
        <div class="img-wrapper" style="position: relative;">
            <a href="<?= $filename ?>" class="venobox" data-gall="gallery" title="<?= htmlspecialchars(strip_tags($captionHtml)) ?>">
                <img src="<?= $filename ?>" alt="">
            </a>
            <div class="caption" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;flex:1;min-width:0;">
                <?= $captionHtml ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    function render(): string {
        $allFiles = $this->fetchFiles();
        $total_rows = count($allFiles);
        $total_pages = ceil($total_rows / $this->limit);
        $filesPage = array_slice($allFiles, $this->offset, $this->limit);

        // Get all folders inside public
        $publicFolder = realpath(\App\Core\SpwBase::getInstance()->getProjectPath() . '/public');
        $folders = array_filter(glob($publicFolder . '/*'), 'is_dir');

        // Apply validFolders filter if set
        if (!empty($this->validFolders)) {
            $folders = array_filter($folders, function($f) {
                return in_array(basename($f), $this->validFolders);
            });
        }

	ob_start();
        ?>
        <div class="album-container">
            <div style="display: flex; align-items: center; margin-bottom: 20px; gap: 10px;">
                <a href="dashboard.php" title="Show Gallery" style="text-decoration: none; font-size: 24px; display: inline-block;">
                    &#x1F5BC;
                </a>
                <h2 style="margin: 0;"><?= $this->getGalleryTitle() ?></h2>
            </div>

            <form style="position: relative; height: 40px;" id="galleryFilterForm" class="gallery-header" method="get" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                <input type="hidden" name="grid" value="<?= $this->gridOn ? '1' : '0' ?>">
                <button style="position: absolute; top: 0; left: <?= $this->getToggleButtonLeft() ?>px;" id="toggleView" type="button">⠿ Grid</button>

                <select id="folderSelect" name="folder" style="position:absolute; top:0; left:<?= $this->getToggleButtonLeft() + 80 ?>px;">
                    <?php foreach($folders as $f): 
                        $folderName = basename($f);
                        $selected = ($folderName === basename($this->folder)) ? 'selected' : '';
                    ?>
                        <option value="<?= htmlspecialchars($folderName) ?>" <?= $selected ?>><?= htmlspecialchars($folderName) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>

            <div class="<?= $this->albumClass ?>">
                <?php foreach ($filesPage as $file): ?>
                    <?= $this->renderItem(['filename' => $file, 'prompt' => '']) ?>
                <?php endforeach; ?>
            </div>

	    <div class="gallery-footer" style="overflow: auto; max-height: 115px; width: 300px; margin-bottom: 50px; background: #eee;">
                <?php
                for ($p = 1; $p <= $total_pages; $p++) {
                    $active = ($p == $this->page) ? 'active' : '';
                    // preserve folder and grid in pagination links
                    $query = $_GET;
                    $query['page'] = $p;
                    $query['folder'] = basename($this->folder);
                    $url = $_SERVER['PHP_SELF'] . '?' . http_build_query($query);
                    echo "<button class=\"$active\" onclick=\"window.location='$url'\">$p</button>";
                }
                ?>
            </div>

            <script>
            document.addEventListener('DOMContentLoaded', function(){
                const album = document.querySelector('.<?= $this->albumClass ?>');
                const toggleBtn = document.getElementById('toggleView');
                const folderSelect = document.getElementById('folderSelect');

                if(album && toggleBtn){
                    let gridOn = <?= $this->gridOn ? 'true' : 'false' ?>;
                    if(gridOn){ album.classList.add('grid'); toggleBtn.textContent='⬜ Pic'; }

                    toggleBtn.addEventListener('click', function(){
                        gridOn = !gridOn;
                        if(gridOn){ album.classList.add('grid'); toggleBtn.textContent='⬜ Pic'; }
                        else { album.classList.remove('grid'); toggleBtn.textContent='⠿ Grid'; }
                        document.querySelector('#galleryFilterForm input[name="grid"]').value = gridOn ? '1' : '0';
                    });
                }








if(folderSelect){
    folderSelect.addEventListener('change', function(){
        const selectedFolder = folderSelect.value;
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('folder', selectedFolder);
        currentUrl.searchParams.delete('page'); // remove page parameter
        window.location = currentUrl.toString();
    });
}






		/*
                if(folderSelect){
                    folderSelect.addEventListener('change', function(){
                        const selectedFolder = folderSelect.value;
                        const currentUrl = new URL(window.location.href);
                        currentUrl.searchParams.set('folder', selectedFolder);
                        window.location = currentUrl.toString();
                    });
	        }
		*/





            });
            </script>

            <?= $this->renderJsCss() ?>
        </div>
        <?php
        return ob_get_clean();
    }

    protected function getCaptionFields(): array {
        return [];
    }

    protected function getGalleryEntity(): string {
        return 'frames';
    }

    protected function getGalleryTitle(): string {
        return "Frames Browser";
    }

    protected function getToggleButtonLeft(): int {
        return 0;
    }
}



