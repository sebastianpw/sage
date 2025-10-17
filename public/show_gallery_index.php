<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Dreamshaper Viewer</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<!-- ScrollMagic -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/ScrollMagic/2.0.8/ScrollMagic.min.js"></script>

<!-- Font Awesome for loader -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

<style>
html, body {
    margin: 0;
    padding: 0;
    background-color: black;
    overflow-x: hidden;
}

#gallery {
    display: flex;
    flex-direction: column;
    align-items: center;
}

img {
    width: 100%;
    max-width: 800px;
    display: block;
    margin: 0 auto;
    opacity: 0;
    transition: opacity 1s ease;
    min-height: 400px; /* reserve vertical space so scroll works even before load */
}

img.loaded {
    opacity: 1;
}

#loader {
    color: white;
    text-align: center;
    padding: 20px;
}
</style>
</head>

<body>

<div id="gallery"></div>
<div id="loader"><i class="fas fa-spinner fa-spin fa-2x"></i> Loading...</div>

<script>
const gallery = document.getElementById('gallery');
const loader = document.getElementById('loader');
let controller = new ScrollMagic.Controller();

let images = [];        // All image URLs
let currentIndex = 0;   // Index of next image to append
const batchSize = 10;    // Number of images per batch

// Fetch all image URLs from your PHP endpoint
async function loadImages() {
    try {
        const response = await fetch('show_gallery_images.php');
        images = await response.json();
        appendNextBatch(); // append first batch
    } catch (err) {
        loader.innerHTML = 'Failed to load images';
        console.error(err);
    }
}

// Append next batch of images and reserve space for scrolling
function appendNextBatch() {
    const end = Math.min(currentIndex + batchSize, images.length);

    for (; currentIndex < end; currentIndex++) {
        const img = document.createElement('img');
        img.dataset.src = images[currentIndex];
        gallery.appendChild(img);

        // Lazy load with ScrollMagic when the image enters viewport
        new ScrollMagic.Scene({
            triggerElement: img,
            triggerHook: 1
        })
        .on('enter', () => {
            if (!img.src) {
                img.src = img.dataset.src;
                img.onload = () => img.classList.add('loaded');
            }
        })
        .addTo(controller);
    }

    // Show loader if there are more images
    loader.style.display = currentIndex < images.length ? 'block' : 'none';

    // Automatically append next batch after short delay
    if (currentIndex < images.length) {
        setTimeout(appendNextBatch, 50);
    }
}

// Start loading
loadImages();
</script>

</body>
</html>
