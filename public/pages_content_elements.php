<?php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

// Handle create new page form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_page_name'])) {
    $name = trim($_POST['new_page_name']);
    if ($name === '') {
        $stmt = $pdo->prepare("INSERT INTO pages (name) VALUES (NULL)");
    } else {
        $stmt = $pdo->prepare("INSERT INTO pages (name) VALUES (:name)");
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
    }
    $stmt->execute();
    header("Location: pages_content_elements.php");
    exit;
}

// Fetch pages from database
$stmt = $pdo->query("SELECT id, name FROM pages WHERE level=1 ORDER BY position ASC");
$pages = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pages & Content Elements</title>
<style>
.collapsible-group { width: 100%; margin-bottom: 15px; }
.group-header { cursor: pointer; padding: 10px 15px; font-weight: bold; font-size: 16px; display: flex; justify-content: space-between; background: #f0f0f0; border-radius: 8px; }
.group-header::after { content: "▼"; display: inline-block; transition: transform 0.2s; transform: rotate(-90deg); }
.group-header.active::after { transform: rotate(0deg); }
.group-content { display: none; padding: 10px 0; }
.page-buttons { margin-bottom: 10px; }
.page-buttons button { margin-right: 5px; }
.content-elements table { width: 100%; border-collapse: collapse; }
.content-elements th, .content-elements td { border: 1px solid #ccc; padding: 5px 10px; text-align: left; }
.content-elements th { background: #eee; }
.new-page-form { margin-bottom: 20px; display: flex; gap: 10px; align-items: center; }
</style>
<script>
document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll(".collapsible-group").forEach((group, index) => {
        const header = group.querySelector(".group-header");
        const content = group.querySelector(".group-content");
        const key = "collapsible_state_" + index;
        const savedState = localStorage.getItem(key);
        if (savedState === "open") { content.style.display = "block"; header.classList.add("active"); }
        header.addEventListener("click", () => {
            const isOpen = content.style.display === "block";
            content.style.display = isOpen ? "none" : "block";
            header.classList.toggle("active");
            localStorage.setItem(key, isOpen ? "closed" : "open");
        });
    });
});
function newContentElement(pageId) {
    window.location.href = `editor.php?page_id=${pageId}`;
}
function editContentElement(pageId, contentElementId) {
    window.location.href = `editor.php?page_id=${pageId}&content_element_id=${contentElementId}`;
}
function showContentElement(pageId, contentElementId) {
    window.open(`statichtml.php?page_id=${pageId}&content_element_id=${contentElementId}`, "_blank");
}
function showPage(pageId) {
    window.open(`statichtml.php?page_id=${pageId}`, "_blank");
}
function downloadPage(pageId) {
    window.location.href = `serve_download.php?page_id=${pageId}`;
}
function downloadContentElement(pageId, contentElementId) {
    window.location.href = `serve_download.php?page_id=${pageId}&content_element_id=${contentElementId}`;
}
</script>
</head>
<body>

<div class="album-container">
    <div style="display: flex; align-items: center; margin-bottom: 20px; gap: 10px;">
        <a href="dashboard.php" title="Show Gallery" 
           style="text-decoration: none; font-size: 24px; display: inline-block;">
            &#x1F5BC;
        </a>
        <h2 style="margin: 0;">
            Pages &amp; Content Elements
        </h2>
    </div>

    <!-- New Page Form -->
    <form method="post" class="new-page-form">
        <input type="text" name="new_page_name" placeholder="Enter page name (optional)">
        <button type="submit">+ Create New Page</button>
    </form>

<?php foreach($pages as $page): ?>
<div class="collapsible-group">
    <div class="group-header"><?= htmlspecialchars($page['name'] ?: "Page #{$page['id']}") ?></div>
    <div class="group-content">
        <div class="page-buttons">
	    <button onclick="newContentElement(<?= $page['id'] ?>)">+ New Content Element</button>
<button onclick="showPage(<?= $page['id'] ?>)">Show Page</button>
            <button onclick="downloadPage(<?= $page['id'] ?>)">📩</button>
        </div>
        <div class="content-elements">
            <?php
            $stmt2 = $pdo->prepare("SELECT id, name FROM content_elements WHERE page_id = :page_id ORDER BY id ASC");
            $stmt2->bindValue(':page_id', $page['id'], PDO::PARAM_INT);
            $stmt2->execute();
            $elements = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            if($elements):
            ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($elements as $el): ?>
                    <tr>
                        <td><?= $el['id'] ?></td>
                        <td><?= htmlspecialchars($el['name'] ?: "(unnamed)") ?></td>
                        <td>
                            <button onclick="editContentElement(<?= $page['id'] ?>, <?= $el['id'] ?>)">Edit</button>
                            <button onclick="showContentElement(<?= $page['id'] ?>, <?= $el['id'] ?>)">Show</button>
                            <button onclick="downloadContentElement(<?= $page['id'] ?>, <?= $el['id'] ?>)">📩</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p>No content elements yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>

</div>

</body>
</html>
