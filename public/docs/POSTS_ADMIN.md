# The SAGE Posts System: User Guide
Welcome to the SAGE Posts Admin! This is your central hub for creating, managing, and publishing content. Whether you're creating a simple image gallery or a complex video series, this guide will walk you through every feature.
### Core Concepts
1.  **Post:** A "post" is a single piece of content, like a blog article, a photo gallery, or a video playlist. Each post has a title, a unique URL, and some media attached to it.
2.  **Post Type:** This is the most important setting for a post. It defines *how* the post will be displayed to your audience. The system supports several types, like an image grid, a swipeable image gallery, a video playlist, and more.
3.  **Static Export:** The system has a powerful feature to "export" your entire collection of posts as a self-contained website (a set of HTML files). This is perfect for creating a fast, secure, and easily shareable archive of your content.
---
### The Posts Admin Screen (Your Dashboard)
This is the main screen where you will see a list of all your posts and perform high-level actions.
#### The Header Bar
At the top of the page, you'll find a set of action buttons:
*   **Create New Post:** This takes you to the form for creating a brand new post from scratch.
*   **View Post Grid:** This opens a new tab showing you exactly what your audience sees—the main grid of all your published posts.
*   **Export Grid HTML:** Downloads a single `index.html` file. This is the main landing page for a static export of your site.
*   **Download All as ZIP:** This is the most powerful export option. It bundles the `index.html` grid page *and* all the individual post pages into a single ZIP file. You can then upload this file anywhere to host a complete, static version of your posts.
#### The Posts List
The main table on this screen gives you an at-a-glance overview of your content. You'll see the post's **Title**, its **Type**, a **Preview** image, its **Sort Order**, and when it was last updated.
For each post, you have several actions:
*   **Edit:** Takes you to the post editing form, pre-filled with that post's details.
*   **Export:** Downloads the individual HTML file for just this single post.
*   **Delete:** Permanently removes the post from the database. A confirmation will be required. **This cannot be undone.**
---
### Creating and Editing a Post (The Form)
This is where you'll spend most of your time crafting your content. The form is the same whether you're creating a new post or editing an existing one.
#### Key Fields
1.  **Title:** The main title of your post.
2.  **URL Slug:** This is the text that will appear in the post's web address (e.g., `.../view.php?slug=my-first-post`). You can leave this blank, and the system will automatically generate a clean, URL-friendly version from your title.
3.  **Sort Order:** This is a number that controls the post's position on the main grid. **Higher numbers appear first.** For example, a post with a sort order of `100` will be shown before a post with a sort order of `90`.
4.  **Post Type:** This critical dropdown determines the layout of your final post. Choose the one that best fits your media:
    *   `Image Grid`: A simple, clean grid of images. Great for showcasing many pictures at once.
    *   `Image Swiper`: A modern, mobile-friendly gallery where users can swipe through one large image at a time.
    *   `Video Playlist`: The perfect choice for a series of your own videos. It features a main video player with a scrollable playlist of thumbnails below it.
    *   `YouTube Playlist`: Use this for embedding a YouTube playlist directly onto the page.
5.  **Preview Image URL:** The URL of the image that will be used as the thumbnail for this post on the main grid page.
6.  **Content (HTML):** The main body of text for your post. You can use basic HTML here (like `<strong>bold</strong>` or `<p>paragraphs</p>`) to format your text.
7.  **Media Items (JSON):** This is the most technical, but most powerful, field. It's where you define the images or videos that will be displayed in your post. The format is a special text format called JSON.
#### Understanding the "Media Items (JSON)" Field
This field expects a list (an array) of items. Each item is an object with specific keys.
*   **For Image Posts (`Image Grid`, `Image Swiper`):**
    Each item in the array should look like this. `src` is the URL to the full-size image, and `w` and `h` are its width and height.
        [
      { "src": "/path/to/image1.jpg", "w": 1024, "h": 1024, "alt": "Description 1" },
      { "src": "/path/to/image2.jpg", "w": 1024, "h": 1024, "alt": "Description 2" }
    ]
    
*   **For Video Playlists (`Video Playlist`):**
    This is where you can paste the JSON output from your **Video Management Admin**! Go to the "Playlists" tab in the Video Admin, click the "JSON" button on a playlist, copy the text, and paste it here.
        [
      { "title": "My First Video", "url": "/videos/video1.mp4", "thumbnail": "/videos/thumb1.jpg", ... },
      { "title": "Another Video", "url": "/videos/video2.mp4", "thumbnail": "/videos/thumb2.jpg", ... }
    ]
    
    *Important:* The video system outputs a complete JSON string. For this field, make sure you only paste the content *inside* the main square brackets `[...]`.
*   **For YouTube Playlists (`YouTube Playlist`):**
    This is the simplest. The array should contain just one item with the full "embed" URL for the YouTube playlist.
        [
      { "url": "https://www.youtube.com/embed/videoseries?list=PL..." }
    ]
    
Once you've filled out all the fields, click **Save Post** to publish your work
