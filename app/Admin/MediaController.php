<?php

declare(strict_types=1);

namespace App\Admin;

/**
 * Media management controller
 */
final class MediaController extends Controller
{
    private string $mediaPath;
    private string $mediaUrl;
    private array $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private int $maxFileSize = 10485760; // 10MB

    public function __construct()
    {
        parent::__construct();
        $this->mediaPath = dirname(__DIR__, 2) . '/public/assets';
        $this->mediaUrl = '/assets';
    }

    /**
     * Show media library
     */
    public function index(): void
    {
        $this->requireAuth();

        $folder = $this->get('folder', '');
        $folder = $this->sanitizePathSegment($folder);

        $currentPath = $this->mediaPath . ($folder ? '/' . $folder : '');

        if (!is_dir($currentPath)) {
            $currentPath = $this->mediaPath;
            $folder = '';
        }

        $items = $this->scanDirectory($currentPath, $folder);
        // Picker mode: ?picker=1 from the article editor's hero-image
        // browser. Strips chrome and changes file-click to postMessage.
        $isPicker = (bool) $this->get('picker', false);

        $data = [
            'pageTitle' => 'Media Library',
            'items' => $items,
            'currentFolder' => $folder,
            'breadcrumbs' => $this->getBreadcrumbs($folder),
            'csrf' => $this->generateCsrf(),
            'flash' => $this->getFlash(),
            'isPicker' => $isPicker,
        ];

        if ($isPicker) {
            // Chrome-less render so the iframe doesn't show admin
            // sidebar+header inside the modal.
            $this->renderWithoutLayout('media/index', $data);
            return;
        }

        $this->render('media/index', $data);
    }

    /**
     * Upload file(s)
     */
    public function upload(): void
    {
        $this->requireAuth();

        if (!$this->validateCsrf()) {
            $this->flash('error', 'Invalid request');
            $this->redirect('/admin/media');
            return;
        }

        $folder = $this->post('folder', '');
        $folder = $this->sanitizePathSegment($folder);

        $targetDir = $this->mediaPath . ($folder ? '/' . $folder : '');

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        $uploaded = 0;
        $errors = [];

        if (!empty($_FILES['files']['name'][0])) {
            $files = $this->normalizeFiles($_FILES['files']);

            foreach ($files as $file) {
                $result = $this->processUpload($file, $targetDir);
                if ($result === true) {
                    $uploaded++;
                } else {
                    $errors[] = $result;
                }
            }
        }

        if ($uploaded > 0) {
            $this->flash('success', "Uploaded {$uploaded} file(s)");
        }

        foreach ($errors as $error) {
            $this->flash('error', $error);
        }

        $redirectUrl = '/admin/media' . ($folder ? '?folder=' . urlencode($folder) : '');
        $this->redirect($redirectUrl);
    }

    /**
     * Create folder
     */
    public function createFolder(): void
    {
        $this->requireAuth();

        if (!$this->validateCsrf()) {
            $this->flash('error', 'Invalid request');
            $this->redirect('/admin/media');
            return;
        }

        $parentFolder = $this->post('parent_folder', '');
        $parentFolder = $this->sanitizePathSegment($parentFolder);

        $newFolder = $this->post('folder_name', '');
        $newFolder = $this->sanitizePathSegment($newFolder);

        if (empty($newFolder)) {
            $this->flash('error', 'Folder name is required');
            $this->redirect('/admin/media');
            return;
        }

        $targetPath = $this->mediaPath . ($parentFolder ? '/' . $parentFolder : '') . '/' . $newFolder;

        if (is_dir($targetPath)) {
            $this->flash('error', 'Folder already exists');
        } elseif (mkdir($targetPath, 0775, true)) {
            $this->flash('success', "Created folder: {$newFolder}");
        } else {
            $this->flash('error', 'Failed to create folder');
        }

        $redirectUrl = '/admin/media' . ($parentFolder ? '?folder=' . urlencode($parentFolder) : '');
        $this->redirect($redirectUrl);
    }

    /**
     * Delete file or folder
     */
    public function delete(): void
    {
        $this->requireAuth();

        if (!$this->validateCsrf()) {
            $this->json(['success' => false, 'error' => 'Invalid request'], 400);
            return;
        }

        $path = $this->post('path', '');
        $path = str_replace(['..', "\0"], '', $path);

        $fullPath = $this->mediaPath . '/' . ltrim($path, '/');

        if (!file_exists($fullPath)) {
            $this->json(['success' => false, 'error' => 'File not found'], 404);
            return;
        }

        // Don't allow deleting the root assets folder
        if (realpath($fullPath) === realpath($this->mediaPath)) {
            $this->json(['success' => false, 'error' => 'Cannot delete root folder'], 400);
            return;
        }

        if (is_dir($fullPath)) {
            // Only delete empty folders
            if (count(scandir($fullPath)) > 2) {
                $this->json(['success' => false, 'error' => 'Folder is not empty'], 400);
                return;
            }
            rmdir($fullPath);
        } else {
            unlink($fullPath);
        }

        $this->json(['success' => true]);
    }

    /**
     * Get file info (for modal)
     */
    public function info(): void
    {
        $this->requireAuth();

        $path = $this->get('path', '');
        $path = str_replace(['..', "\0"], '', $path);

        $fullPath = $this->mediaPath . '/' . ltrim($path, '/');

        if (!file_exists($fullPath) || is_dir($fullPath)) {
            $this->json(['success' => false, 'error' => 'File not found'], 404);
            return;
        }

        $info = [
            'name' => basename($fullPath),
            'path' => $path,
            'url' => $this->mediaUrl . '/' . ltrim($path, '/'),
            'size' => filesize($fullPath),
            'size_human' => $this->formatBytes(filesize($fullPath)),
            'modified' => date('Y-m-d H:i:s', filemtime($fullPath)),
            'type' => mime_content_type($fullPath),
        ];

        // Add image dimensions if it's an image
        if (str_starts_with($info['type'], 'image/')) {
            $dimensions = @getimagesize($fullPath);
            if ($dimensions) {
                $info['width'] = $dimensions[0];
                $info['height'] = $dimensions[1];
            }
        }

        $this->json(['success' => true, 'file' => $info]);
    }

    /**
     * Scan directory and return items
     */
    private function scanDirectory(string $path, string $folder): array
    {
        $items = [];
        // Media library = flat list of every image under public/assets,
        // recursively. Folder navigation was noise -- hero/inline/css/js
        // dirs were dead-ends, and an editor picking a hero image just
        // wants to see all available images at once. Search by name with
        // Ctrl+F is fast enough at the scales these sites run (< 1000
        // images per site).
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif', 'bmp', 'tiff', 'ico'];

        foreach ($this->iterateImagesRecursive($path, $folder, $imageExtensions) as $item) {
            $items[] = $item;
        }

        // Sort: newest first by modified time. Operators usually want to
        // grab the asset they uploaded recently.
        usort($items, fn($a, $b) => ($b['modified'] ?? 0) <=> ($a['modified'] ?? 0));

        return $items;
    }

    /**
     * Walk the media root recursively, yielding only image files.
     *
     * Path returned in each item is relative to the media root (so the
     * /content/<rel> URL maps cleanly), and includes the subfolder so
     * the operator can read the origin at a glance.
     *
     * @param list<string> $imageExtensions
     * @return iterable<array<string,mixed>>
     */
    private function iterateImagesRecursive(string $rootPath, string $rootRel, array $imageExtensions): iterable
    {
        if (!is_dir($rootPath)) return;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($rootPath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) continue;
            $ext = strtolower($file->getExtension());
            if (!in_array($ext, $imageExtensions, true)) continue;

            // Path relative to media root, normalised to forward slashes.
            $relativeFromRoot = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($rootPath))), '/');
            $relativePath = ($rootRel ? $rootRel . '/' : '') . $relativeFromRoot;

            yield [
                'name'       => $file->getFilename(),
                'path'       => $relativePath,
                'is_dir'     => false,
                'url'        => $this->mediaUrl . '/' . $relativePath,
                'size'       => $file->getSize(),
                'size_human' => $this->formatBytes($file->getSize()),
                'type'       => mime_content_type($file->getPathname()) ?: 'image/*',
                'is_image'   => true,
                'modified'   => $file->getMTime(),
                'subdir'     => trim(dirname($relativeFromRoot), '.'),
            ];
        }
    }

    /**
     * Get breadcrumbs for folder navigation
     */
    private function getBreadcrumbs(string $folder): array
    {
        $breadcrumbs = [['name' => 'Media', 'path' => '']];

        if ($folder) {
            $parts = explode('/', $folder);
            $path = '';
            foreach ($parts as $part) {
                $path .= ($path ? '/' : '') . $part;
                $breadcrumbs[] = ['name' => $part, 'path' => $path];
            }
        }

        return $breadcrumbs;
    }

    /**
     * Normalize $_FILES array for multiple uploads
     */
    private function normalizeFiles(array $files): array
    {
        $normalized = [];

        foreach ($files['name'] as $i => $name) {
            $normalized[] = [
                'name' => $name,
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i],
            ];
        }

        return $normalized;
    }

    /**
     * Process single file upload
     *
     * @return true|string True on success, error message on failure
     */
    private function processUpload(array $file, string $targetDir)
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return "Upload error for {$file['name']}: " . $this->getUploadError($file['error']);
        }

        if ($file['size'] > $this->maxFileSize) {
            return "{$file['name']}: File too large (max 10MB)";
        }

        $mimeType = mime_content_type($file['tmp_name']);
        if (!in_array($mimeType, $this->allowedTypes)) {
            return "{$file['name']}: Invalid file type ({$mimeType})";
        }

        $filename = $this->sanitizeFilename($file['name']);
        $targetPath = $targetDir . '/' . $filename;

        // Avoid overwriting - add number suffix
        $i = 1;
        $pathInfo = pathinfo($filename);
        while (file_exists($targetPath)) {
            $filename = $pathInfo['filename'] . '-' . $i . '.' . ($pathInfo['extension'] ?? '');
            $targetPath = $targetDir . '/' . $filename;
            $i++;
        }

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            chmod($targetPath, 0664);
            return true;
        }

        return "{$file['name']}: Failed to save file";
    }

    /**
     * Get human-readable upload error
     */
    private function getUploadError(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File too large',
            UPLOAD_ERR_PARTIAL => 'Incomplete upload',
            UPLOAD_ERR_NO_FILE => 'No file uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Server error (no temp dir)',
            UPLOAD_ERR_CANT_WRITE => 'Server error (write failed)',
            default => 'Unknown error',
        };
    }

    /**
     * Format bytes to human readable
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 1) . ' ' . $units[$i];
    }
}
