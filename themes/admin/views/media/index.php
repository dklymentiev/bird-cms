<?php
/**
 * Media Library View
 *
 * Variables:
 * - $items: array - Files and folders
 * - $currentFolder: string - Current folder path
 * - $breadcrumbs: array - Breadcrumb navigation
 * - $csrf: string - CSRF token
 * - $flash: array - Flash messages
 * - $isPicker: bool - True when iframed by the editor's hero-image picker
 */
$isPicker = $isPicker ?? false;
?>
<?php if ($isPicker): ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title>Media picker</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="/admin/assets/brand.css">
<link rel="stylesheet" href="/admin/assets/admin.css">
</head>
<body class="bg-slate-900 text-slate-200 p-4">
<?php endif; ?>

<div x-data="{ q: '' }">
<div class="mb-6 flex items-center justify-between">
    <div class="flex items-center gap-4 flex-1">
        <?php if (!$isPicker): ?>
        <h1 class="text-2xl font-bold text-slate-100">Media Library</h1>
        <?php endif; ?>
        <span class="text-sm text-slate-400"><?= count($items) ?> <?= count($items) === 1 ? 'image' : 'images' ?></span>
        <!-- Live filter: client-side, hides tiles whose name doesn't match.
             Cheap at the < 1000 image scale these sites run; no backend round-trip. -->
        <div class="flex-1 max-w-md">
            <div class="relative">
                <i class="ri-search-line text-base text-slate-500 absolute left-3 top-1/2 -translate-y-1/2 leading-none"></i>
                <input type="text" x-model="q" placeholder="Filter by name..."
                       class="w-full pl-9 pr-3 py-1.5 bg-slate-900 border border-slate-700 text-sm text-slate-200 placeholder-slate-500 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
        </div>
    </div>
    <?php if (!$isPicker): ?>
    <div class="flex items-center space-x-2">
        <button onclick="document.getElementById('uploadModal').classList.remove('hidden')"
                class="inline-flex items-center px-4 py-2 bg-blue-900 text-blue-200 hover:bg-blue-800 transition-colors border border-blue-700">
            <i class="ri-upload-line text-lg mr-2 leading-none"></i>
            Upload
        </button>
    </div>
    <?php else: ?>
    <div class="text-xs text-slate-400">Click an image to insert</div>
    <?php endif; ?>
</div>

<!-- Files Grid -->
<div class="bg-white rounded-lg shadow-sm border border-gray-200">
    <?php if (empty($items)): ?>
        <div class="p-12 text-center text-gray-500">
            <i class="ri-image-line text-5xl text-gray-300 block mb-4 leading-none"></i>
            <p>No files in this folder</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4 p-4">
            <?php foreach ($items as $item): ?>
                <!-- File tile (folders removed -- media library is flat now) -->
                <div class="group relative flex flex-col items-center p-4 hover:bg-slate-800 transition-colors cursor-pointer"
                     x-show="q === '' || '<?= htmlspecialchars(strtolower($item['name'].' '.($item['subdir'] ?? '')), ENT_QUOTES) ?>'.includes(q.toLowerCase())"
                     <?php if ($isPicker): ?>
                     onclick="window.parent.postMessage({bird:'media-pick', url:<?= json_encode($item['url'], JSON_HEX_QUOT|JSON_HEX_APOS) ?>}, '*')"
                     <?php else: ?>
                     onclick="showFileInfo('<?= htmlspecialchars($item['path'], ENT_QUOTES) ?>')"
                     <?php endif; ?>>
                    <div class="w-full aspect-square overflow-hidden bg-slate-800 flex items-center justify-center">
                        <img src="<?= htmlspecialchars($item['url']) ?>"
                             alt="<?= htmlspecialchars($item['name']) ?>"
                             class="max-w-full max-h-full object-contain"
                             loading="lazy">
                    </div>
                    <span class="mt-2 text-sm text-slate-300 text-center truncate w-full" title="<?= htmlspecialchars($item['name']) ?>"><?= htmlspecialchars($item['name']) ?></span>
                    <?php if (!empty($item['subdir'])): ?>
                        <span class="text-xs text-slate-500 text-center truncate w-full" title="<?= htmlspecialchars($item['subdir']) ?>"><?= htmlspecialchars($item['subdir']) ?></span>
                    <?php endif; ?>
                    <span class="text-xs text-slate-600"><?= $item['size_human'] ?></span>

                    <?php if (!$isPicker): ?>
                    <button onclick="event.stopPropagation(); deleteFile('<?= htmlspecialchars($item['path'], ENT_QUOTES) ?>')"
                            class="absolute top-2 right-2 p-1 bg-red-900 text-red-200 opacity-0 group-hover:opacity-100 transition-opacity"
                            title="Delete">
                        <i class="ri-close-line text-base leading-none"></i>
                    </button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
</div><!-- /x-data q -->

<!-- Upload Modal -->
<div id="uploadModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-lg w-full mx-4">
        <div class="flex items-center justify-between p-4 border-b">
            <h3 class="text-lg font-semibold">Upload Files</h3>
            <button onclick="document.getElementById('uploadModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
                <i class="ri-close-line text-xl leading-none"></i>
            </button>
        </div>
        <form action="/admin/media/upload" method="POST" enctype="multipart/form-data" class="p-4">
            <input type="hidden" name="_csrf" value="<?= $csrf ?>">
            <input type="hidden" name="folder" value="<?= htmlspecialchars($currentFolder) ?>">

            <div class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center hover:border-blue-400 transition-colors">
                <i class="ri-image-line text-5xl text-gray-400 block mb-4 leading-none"></i>
                <p class="text-gray-600 mb-2">Drag and drop files here or</p>
                <input type="file" name="files[]" id="fileInput" multiple accept="image/*" class="hidden">
                <label for="fileInput" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 cursor-pointer">
                    Browse Files
                </label>
                <p class="text-xs text-gray-400 mt-2">PNG, JPG, GIF, WebP, SVG (max 10MB)</p>
            </div>

            <div id="fileList" class="mt-4 space-y-2"></div>

            <div class="mt-4 flex justify-end space-x-2">
                <button type="button" onclick="document.getElementById('uploadModal').classList.add('hidden')"
                        class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Upload</button>
            </div>
        </form>
    </div>
</div>

<!-- Create Folder Modal -->
<div id="createFolderModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
        <div class="flex items-center justify-between p-4 border-b">
            <h3 class="text-lg font-semibold">Create Folder</h3>
            <button onclick="document.getElementById('createFolderModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
                <i class="ri-close-line text-xl leading-none"></i>
            </button>
        </div>
        <form action="/admin/media/create-folder" method="POST" class="p-4">
            <input type="hidden" name="_csrf" value="<?= $csrf ?>">
            <input type="hidden" name="parent_folder" value="<?= htmlspecialchars($currentFolder) ?>">

            <label class="block text-sm font-medium text-gray-700 mb-1">Folder Name</label>
            <input type="text" name="folder_name" required
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                   placeholder="my-folder">

            <div class="mt-4 flex justify-end space-x-2">
                <button type="button" onclick="document.getElementById('createFolderModal').classList.add('hidden')"
                        class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Create</button>
            </div>
        </form>
    </div>
</div>

<!-- File Info Modal -->
<div id="fileInfoModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4">
        <div class="flex items-center justify-between p-4 border-b">
            <h3 class="text-lg font-semibold" id="fileInfoTitle">File Info</h3>
            <button onclick="document.getElementById('fileInfoModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
                <i class="ri-close-line text-xl leading-none"></i>
            </button>
        </div>
        <div class="p-4">
            <div id="fileInfoContent" class="flex gap-4">
                <div id="filePreview" class="w-1/2 bg-gray-100 rounded-lg flex items-center justify-center min-h-[200px]"></div>
                <div id="fileDetails" class="w-1/2 space-y-3"></div>
            </div>
            <div class="mt-4 pt-4 border-t">
                <label class="block text-sm font-medium text-gray-700 mb-1">URL (click to copy)</label>
                <input type="text" id="fileUrl" readonly
                       class="w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-lg text-sm font-mono cursor-pointer"
                       onclick="copyUrl(this)">
            </div>
        </div>
    </div>
</div>

<script>
const csrfToken = '<?= $csrf ?>';

// File input preview
document.getElementById('fileInput').addEventListener('change', function(e) {
    const fileList = document.getElementById('fileList');
    fileList.innerHTML = '';

    Array.from(e.target.files).forEach(file => {
        const div = document.createElement('div');
        div.className = 'flex items-center justify-between p-2 bg-gray-50 rounded';
        div.innerHTML = `
            <span class="text-sm truncate">${file.name}</span>
            <span class="text-xs text-gray-400">${formatBytes(file.size)}</span>
        `;
        fileList.appendChild(div);
    });
});

function formatBytes(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

async function showFileInfo(path) {
    const response = await fetch('/admin/media/info?path=' + encodeURIComponent(path));
    const data = await response.json();

    if (!data.success) {
        alert('Error loading file info');
        return;
    }

    const file = data.file;
    document.getElementById('fileInfoTitle').textContent = file.name;

    // Preview
    const preview = document.getElementById('filePreview');
    if (file.type.startsWith('image/')) {
        preview.innerHTML = `<img src="${file.url}" class="max-w-full max-h-[300px] object-contain">`;
    } else {
        preview.innerHTML = `<i class="ri-article-line text-7xl text-gray-400 leading-none"></i>`;
    }

    // Details
    let details = `
        <div><span class="text-gray-500">Size:</span> ${file.size_human}</div>
        <div><span class="text-gray-500">Type:</span> ${file.type}</div>
        <div><span class="text-gray-500">Modified:</span> ${file.modified}</div>
    `;
    if (file.width) {
        details += `<div><span class="text-gray-500">Dimensions:</span> ${file.width} x ${file.height}</div>`;
    }
    document.getElementById('fileDetails').innerHTML = details;

    document.getElementById('fileUrl').value = location.origin + file.url;
    document.getElementById('fileInfoModal').classList.remove('hidden');
}

function copyUrl(input) {
    input.select();
    document.execCommand('copy');
    const original = input.value;
    input.value = 'Copied!';
    setTimeout(() => input.value = original, 1000);
}

async function deleteFile(path) {
    if (!confirm('Delete this file?')) return;

    const response = await fetch('/admin/media/delete', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-Token': csrfToken
        },
        body: '_csrf=' + csrfToken + '&path=' + encodeURIComponent(path)
    });

    const data = await response.json();
    if (data.success) {
        location.reload();
    } else {
        alert(data.error || 'Failed to delete');
    }
}

// Drag and drop
const dropZone = document.querySelector('.border-dashed');
['dragenter', 'dragover', 'dragleave', 'drop'].forEach(evt => {
    dropZone.addEventListener(evt, e => {
        e.preventDefault();
        e.stopPropagation();
    });
});

dropZone.addEventListener('dragover', () => dropZone.classList.add('border-blue-400', 'bg-blue-50'));
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('border-blue-400', 'bg-blue-50'));
dropZone.addEventListener('drop', e => {
    dropZone.classList.remove('border-blue-400', 'bg-blue-50');
    document.getElementById('fileInput').files = e.dataTransfer.files;
    document.getElementById('fileInput').dispatchEvent(new Event('change'));
});
</script>

<?php if ($isPicker): ?>
</body>
</html>
<?php endif; ?>
