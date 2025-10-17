<?php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Simple WYSIWYG Editor</title>

<?php /*
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
 */ ?>

<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
    <!-- Bootstrap CSS via CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<?php else: ?>
    <!-- Bootstrap CSS via local copy -->
    <link href="/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
<?php endif; ?>

<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
    <!-- Bootstrap Bundle JS via CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php else: ?>
    <!-- Bootstrap Bundle JS via local copy -->
    <script src="/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<?php endif; ?>

<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
    <!-- jQuery via CDN -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<?php else: ?>
    <!-- jQuery via local copy -->
    <script src="/vendor/jquery/jquery-3.7.0.min.js"></script>
<?php endif; ?>




<style>
    #editor { border: 1px solid #ccc; min-height: 200px; padding: 10px; }
    .frame-container img { width: 120px; cursor: pointer; border: 1px solid #ccc; margin: 5px; }
    #framesModal { max-height: 400px; overflow:auto; }
    .editor-grid { display: flex; border: 1px dashed #ccc; margin-bottom: 10px; }
    .editor-column { flex: 1; border-right: 1px dashed #ccc; padding: 5px; min-height: 100px; }
    .editor-column:last-child { border-right: none; }
</style>

<?php // echo $eruda; ?>
</head>
<body class="container mt-4">

<h1>Simple WYSIWYG Editor</h1>

<div class="mb-2">
    <button class="btn btn-secondary me-1" id="boldBtn"><b>B</b></button>
    <button class="btn btn-secondary me-1" id="italicBtn"><i>I</i></button>
    <button class="btn btn-secondary me-1" id="underlineBtn"><u>U</u></button>
    <button class="btn btn-primary me-1" id="addImageBtn">Add Image</button>
    <button class="btn btn-primary me-1" id="addGridBtn">Add Grid</button>
    <button class="btn btn-success" id="copyHtmlBtn">Copy HTML</button>
    <button class="btn btn-info" id="saveBtn">Save</button>
</div>

<div id="editor" contenteditable="true" class="mb-3"></div>

<!-- Frames modal -->
<div id="framesModal" class="p-3 border bg-light" style="display:none;">
    <h5>Select an Image</h5>
    <div class="frame-container" id="frames"></div>
    <div class="mt-2">
        <button class="btn btn-sm btn-secondary" id="prevFrames">Previous</button>
        <button class="btn btn-sm btn-secondary" id="nextFrames">Next</button>
        <button class="btn btn-sm btn-danger" id="closeModal">Close</button>
    </div>
</div>

<script>
class Editor {





	constructor() {
    this.offset = 0;
    this.limit = 10;
    this.contentElementId = 0;
    this.pageId = 0;

    // Read page_id and content_element_id from URL
    const urlParams = new URLSearchParams(window.location.search);
    this.pageId = parseInt(urlParams.get('page_id')) || 0;
    this.contentElementId = parseInt(urlParams.get('content_element_id')) || 0;

    if (!this.pageId) {
        alert('Error: missing page_id');
        return;
    }

    this.loadContent();
    this.initButtons();
}




/*


constructor() {
    this.offset = 0;
    this.limit = 10;
    this.contentElementId = 0;
    this.pageId = 0;

    // Read page_id from URL immediately
    const urlParams = new URLSearchParams(window.location.search);
    this.pageId = parseInt(urlParams.get('page_id')) || 0;
    if(!this.pageId){
        alert('Error: missing page_id');
        return;
    }

    this.initButtons();   // <-- initialize buttons first
    this.loadContent();   // <-- then load content
}

 */

	/*
    constructor() {
        this.offset = 0;
        this.limit = 10;
        this.contentElementId = 0; 
        this.pageId = 0; 

        // Read page_id from URL immediately
        const urlParams = new URLSearchParams(window.location.search);
        this.pageId = parseInt(urlParams.get('page_id')) || 0;
        if(!this.pageId) { 
            alert('Error: missing page_id'); 
            return; 
        }

        this.loadContent();
        this.initButtons();
    }
*/








    initButtons() {
        $('#boldBtn').click(() => document.execCommand('bold'));
        $('#italicBtn').click(() => document.execCommand('italic'));
        $('#underlineBtn').click(() => document.execCommand('underline'));

        $('#copyHtmlBtn').click(() => this.copyHtml());
        $('#saveBtn').click(() => this.saveContent());

        $('#addImageBtn').click(() => this.showFramesModal());
        $('#closeModal').click(() => $('#framesModal').hide());
        $('#nextFrames').click(() => { this.offset += this.limit; this.loadFrames(); });
        $('#prevFrames').click(() => { this.offset = Math.max(this.offset - this.limit, 0); this.loadFrames(); });

        $('#addGridBtn').click(() => {
            const columns = prompt("Enter number of columns (1-4):", 3);
            if (columns >= 1 && columns <= 4) this.insertGrid(columns);
        });

        $(document).on('click', '#frames img', (e) => this.insertImage(e));
    }







	loadContent() {
    $.ajax({
        url: 'editor_load_content.php',
        method: 'GET',
        data: {
            page_id: this.pageId,
            content_element_id: this.contentElementId
        },
        dataType: 'json',
        success: (res) => {
            if (res.error) {
                alert('Error loading content: ' + res.error);
                return;
            }
            $('#editor').html(res.html);
            this.contentElementId = res.id || 0;
            // update URL
            history.replaceState(null, '', '?page_id=' + this.pageId + '&content_element_id=' + this.contentElementId);
            console.log('Content loaded:', { pageId: this.pageId, contentElementId: this.contentElementId });
        },
        error: (xhr, status, err) => console.error("Load content error:", err)
    });
}






	/*
    loadContent() {
        // Load existing content_element or create new
        $.ajax({
            url: 'editor_load_content.php',
            method: 'GET',
            data: { 
                content_element_id: 0, 
                page_id: this.pageId 
            },
            dataType: 'json',
            success: (res) => {
                if(res.error) {
                    alert('Error loading content: ' + res.error);
                    return;
                }
                $('#editor').html(res.html);
                this.contentElementId = res.id || 0;
                // Keep pageId from URL
                history.replaceState(null, '', '?content_element_id=' + this.contentElementId + '&page_id=' + this.pageId);
                console.log('Content loaded:', { pageId: this.pageId, contentElementId: this.contentElementId });
            },
            error: (xhr, status, err) => console.error("Load content error:", err)
        });
    }

	 */










    saveContent() {
    if(!this.pageId) {
        alert('Error: missing page_id');
        return;
    }

    const html = $('#editor').html();
    console.log('Saving content:', { pageId: this.pageId, contentElementId: this.contentElementId });

    $.ajax({
        url: 'editor_save_content.php',
        method: 'POST',
        data: {
            html: html,
            page_id: this.pageId,
            content_element_id: this.contentElementId
        },
        dataType: 'json',
        success: (res) => {
            if(res.error){
                alert('Save error: ' + res.error);
                return;
            }
            this.contentElementId = res.id;  // <-- make sure we update it after save
            console.log('Content saved:', res);
            alert('Content saved!');
            history.replaceState(null, '', '?content_element_id=' + this.contentElementId + '&page_id=' + this.pageId);
        },
        error: (xhr, status, err) => console.error("Save error:", err)
    });
}






    /*
    saveContent() {
        if(!this.pageId) { alert('Error: missing page_id'); return; }
        const html = $('#editor').html();
        console.log('Saving content:', { pageId: this.pageId, contentElementId: this.contentElementId });
        $.ajax({
            url: 'editor_save_content.php',
            method: 'POST',
            data: { html, content_element_id: this.contentElementId, page_id: this.pageId },
            dataType: 'json',
            success: (res) => {
                if(res.error) { alert('Save error: ' + res.error); return; }
                this.contentElementId = res.id;
                console.log('Content saved:', res);
                alert('Content saved!');
                history.replaceState(null, '', '?content_element_id=' + this.contentElementId + '&page_id=' + this.pageId);
            },
            error: (xhr, status, err) => console.error("Save error:", err)
        });
    }

     */







    copyHtml() {
        const $clone = $('#editor').clone();
        $clone.find('img').each(function() {
            const relative = $(this).attr('data-src');
            if (relative) $(this).attr('src', relative);
        });
        const htmlContent = $clone.html();

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(htmlContent).then(() => alert('HTML copied to clipboard!'));
        } else {
            const temp = $('<textarea>').val(htmlContent).appendTo('body').select();
            document.execCommand('copy'); temp.remove();
            alert('HTML copied to clipboard (fallback)!');
        }
    }

    showFramesModal() { this.offset = 0; $('#framesModal').show(); this.loadFrames(); }
    loadFrames() {
        $.ajax({
            url: 'editor_load_frames.php',
            method: 'GET',
            data: { offset: this.offset, limit: this.limit },
            dataType: 'json',
            success: (frames) => {
                $('#frames').empty();
                frames.forEach(f => $('#frames').append(`<img src="${f.url}" data-src="${f.url}" alt="${f.name}" title="${f.name}">`));
                $('#prevFrames').prop('disabled', this.offset === 0);
                $('#nextFrames').prop('disabled', frames.length < this.limit);
            },
            error: (xhr, status, err) => console.error("Frames load error:", err)
        });
    }

    insertImage(e) {
        const img = $(e.currentTarget);
        const src = img.attr('data-src');
        const title = img.attr('title');
        const html = `<a href="${src}" class="venobox" data-gall="gallery" title="${title}">
                        <img src="${src}" data-src="${src}" alt="${title}" style="max-width:100%;">
                      </a>`;
        this.insertAtCursor(html);
        $('#framesModal').hide();
    }

    insertGrid(columns) {
        let gridHTML = '<div class="editor-grid">';
        for (let i=0; i<columns; i++) gridHTML += '<div class="editor-column" contenteditable="true"></div>';
        gridHTML += '</div>';
        this.insertAtCursor(gridHTML);
    }

    insertAtCursor(html) {
        const sel = window.getSelection();
        if (!sel.rangeCount) return;
        const range = sel.getRangeAt(0);
        range.deleteContents();
        const el = document.createElement("div");
        el.innerHTML = html;
        const frag = document.createDocumentFragment();
        let node;
        while ((node = el.firstChild)) frag.appendChild(node);
        range.insertNode(frag);
        range.collapse(false);
    }
}

const editor = new Editor();
</script>

</body>
</html>
