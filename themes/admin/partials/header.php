<?php
/**
 * Admin Header
 */
$username = $auth->username() ?? 'Admin';
?>

<header class="bg-slate-900 border-b border-slate-700 px-6 py-4">
    <div class="flex items-center justify-between">
        <!-- Page title with back button -->
        <div class="flex items-center gap-3">
            <!-- Mobile hamburger: shows below lg, opens sidebar drawer. -->
            <button @click="sidebarOpen = !sidebarOpen"
                    class="lg:hidden p-1.5 text-slate-400 hover:text-slate-200 hover:bg-slate-800 transition-colors"
                    title="Menu">
                <i class="ri-menu-line text-lg leading-none"></i>
            </button>
            <button onclick="history.back()"
                    class="p-1.5 text-slate-500 hover:text-slate-300 hover:bg-slate-800 transition-colors"
                    title="Go back">
                <i class="ri-arrow-left-s-line text-lg leading-none"></i>
            </button>
            <?php if (!empty($headerCrumbs) && is_array($headerCrumbs)): ?>
                <nav class="flex items-center gap-2 text-xl font-semibold text-slate-100" aria-label="Breadcrumb">
                    <?php $last = count($headerCrumbs) - 1; foreach ($headerCrumbs as $i => $crumb): ?>
                        <?php $label = htmlspecialchars((string) ($crumb[0] ?? '')); ?>
                        <?php if ($i < $last && !empty($crumb[1])): ?>
                            <a href="<?= htmlspecialchars((string) $crumb[1]) ?>"
                               class="text-slate-400 hover:text-slate-200 transition-colors"><?= $label ?></a>
                        <?php else: ?>
                            <span class="<?= $i === $last ? 'text-slate-100' : 'text-slate-400' ?>"><?= $label ?></span>
                        <?php endif; ?>
                        <?php if ($i < $last): ?>
                            <span class="text-slate-600" aria-hidden="true">/</span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </nav>
            <?php else: ?>
                <h1 class="text-xl font-semibold text-slate-100">
                    <?= htmlspecialchars($pageTitle ?? 'Dashboard') ?>
                </h1>
            <?php endif; ?>
        </div>

        <!-- Right side -->
        <div class="flex items-center space-x-4">
            <!-- View live site -->
            <a href="/" target="_blank" rel="noopener noreferrer"
               class="inline-flex items-center text-sm text-slate-400 hover:text-slate-100 transition-colors"
               title="Open the public site in a new tab">
                <i class="ri-external-link-line text-base mr-1.5 leading-none"></i>
                View site
            </a>

            <!-- Quick actions -->
            <a href="/admin/articles/new"
               class="inline-flex items-center px-4 py-2 bg-blue-900 text-blue-200 text-sm font-medium hover:bg-blue-800 transition-colors border border-blue-700">
                <i class="ri-add-line text-base mr-2 leading-none"></i>
                New Article
            </a>

            <!-- User dropdown -->
            <div x-data="{ open: false }" class="relative">
                <button @click="open = !open"
                        class="flex items-center space-x-2 text-slate-400 hover:text-slate-200">
                    <div class="w-8 h-8 bg-slate-700 flex items-center justify-center border border-slate-600">
                        <span class="text-sm font-medium text-slate-300">
                            <?= strtoupper(substr($username, 0, 1)) ?>
                        </span>
                    </div>
                    <span class="text-sm font-medium"><?= htmlspecialchars($username) ?></span>
                    <i class="ri-arrow-down-s-line text-base leading-none"></i>
                </button>

                <!-- Dropdown menu -->
                <div x-show="open"
                     x-cloak
                     @click.away="open = false"
                     class="absolute right-0 mt-2 w-48 bg-slate-800 shadow-lg border border-slate-700 py-1 z-50">
                    <form method="POST" action="/admin/logout">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                        <button type="submit"
                                class="block w-full text-left px-4 py-2 text-sm text-slate-300 hover:bg-slate-700 hover:text-white">
                            Sign out
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>
