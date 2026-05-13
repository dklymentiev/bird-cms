<?php
/**
 * Step 2 - site + admin identity.
 * Provided by Wizard::renderStep2():
 *   $values     array<string,string> (form values, including timezone default)
 *   $errors     array<string,string> (field => message; empty on first render)
 *   $timezones  list<string> (DateTimeZone::listIdentifiers())
 *   $csrf       string
 */
$err = static function(string $field) use ($errors) {
    return isset($errors[$field])
        ? '<span class="error">' . htmlspecialchars($errors[$field], ENT_QUOTES, 'UTF-8') . '</span>'
        : '';
};
$inv = static function(string $field) use ($errors) {
    return isset($errors[$field]) ? 'aria-invalid="true"' : '';
};
$val = static function(string $field) use ($values) {
    return htmlspecialchars((string)($values[$field] ?? ''), ENT_QUOTES, 'UTF-8');
};
?>
<h2>Tell us about your site</h2>
<p class="lead">Five fields. You can change all of these later in the admin settings.</p>

<?php if (!empty($errors['_csrf'])): ?>
    <div class="banner banner-fail"><?= htmlspecialchars($errors['_csrf'], ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<form method="POST" action="/install/identity" autocomplete="off">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

    <div class="field">
        <label for="site_name">Site name</label>
        <input type="text" id="site_name" name="site_name" required maxlength="100"
               value="<?= $val('site_name') ?>" <?= $inv('site_name') ?> placeholder="My Site">
        <?= $err('site_name') ?>
    </div>

    <div class="field">
        <label for="site_url">Site URL</label>
        <input type="url" id="site_url" name="site_url" required
               value="<?= $val('site_url') ?>" <?= $inv('site_url') ?>>
        <?= $err('site_url') ?>
    </div>

    <div class="field">
        <label for="site_description">Description (optional)</label>
        <input type="text" id="site_description" name="site_description" maxlength="200"
               value="<?= $val('site_description') ?>" placeholder="One-sentence elevator pitch.">
    </div>

    <div class="field-row cols-2">
        <div class="field">
            <label for="admin_email">Admin email</label>
            <input type="email" id="admin_email" name="admin_email" required
                   value="<?= $val('admin_email') ?>" <?= $inv('admin_email') ?>>
            <?= $err('admin_email') ?>
        </div>
        <div class="field">
            <label for="admin_username">Admin username</label>
            <input type="text" id="admin_username" name="admin_username" required
                   pattern="[a-zA-Z0-9_-]{3,32}"
                   value="<?= $val('admin_username') ?>" <?= $inv('admin_username') ?>>
            <?= $err('admin_username') ?>
        </div>
    </div>

    <div class="field">
        <label for="admin_password">Admin password</label>
        <div class="password-row">
            <input type="password" id="admin_password" name="admin_password" required
                   minlength="8" autocomplete="new-password" <?= $inv('admin_password') ?>>
            <button type="button" class="btn btn-secondary btn-inline" id="admin_password_generate"
                    aria-label="Generate a strong password">Generate</button>
        </div>
        <small class="field-hint">At least 8 characters, mixing letters and digits.</small>
        <span class="field-hint field-hint-success" id="admin_password_feedback" hidden>
            Generated and shown above &mdash; copy it somewhere safe before continuing.
        </span>
        <?= $err('admin_password') ?>
    </div>

    <div class="field-row cols-2">
        <div class="field">
            <label for="timezone">Timezone</label>
            <select id="timezone" name="timezone" required <?= $inv('timezone') ?>>
                <?php $current = (string)($values['timezone'] ?? 'UTC'); ?>
                <?php foreach ($timezones as $tz): ?>
                    <option value="<?= htmlspecialchars($tz, ENT_QUOTES, 'UTF-8') ?>"
                        <?= $tz === $current ? 'selected' : '' ?>>
                        <?= htmlspecialchars($tz, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?= $err('timezone') ?>
        </div>
        <div class="field">
            <label for="language">Language</label>
            <select id="language" name="language">
                <option value="en" selected>English</option>
            </select>
        </div>
    </div>

    <div class="actions">
        <a href="/install" class="btn btn-secondary">&larr; Back</a>
        <button type="submit" class="btn btn-primary right">Continue &rarr;</button>
    </div>
</form>

<script>
(function () {
    var input    = document.getElementById('admin_password');
    var btn      = document.getElementById('admin_password_generate');
    var feedback = document.getElementById('admin_password_feedback');
    if (!input || !btn) { return; }

    // Ambiguous glyphs (0/O, 1/l/I) removed so a user copying by eye doesn't misread.
    var LETTERS = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
    var DIGITS  = '23456789';
    var ALPHA   = LETTERS + DIGITS;
    var LENGTH  = 16;

    function randomIndex(max) {
        if (window.crypto && window.crypto.getRandomValues) {
            var buf = new Uint32Array(1);
            window.crypto.getRandomValues(buf);
            return buf[0] % max;
        }
        return Math.floor(Math.random() * max);
    }

    function pick(alphabet) {
        return alphabet.charAt(randomIndex(alphabet.length));
    }

    function shuffle(chars) {
        for (var i = chars.length - 1; i > 0; i--) {
            var j = randomIndex(i + 1);
            var tmp = chars[i]; chars[i] = chars[j]; chars[j] = tmp;
        }
        return chars.join('');
    }

    function generate() {
        // Guarantee the validator's "at least one letter and one digit" rule.
        var out = [pick(LETTERS), pick(DIGITS)];
        while (out.length < LENGTH) { out.push(pick(ALPHA)); }
        return shuffle(out);
    }

    btn.addEventListener('click', function () {
        input.type = 'text';
        input.value = generate();
        input.focus();
        input.select();
        if (feedback) { feedback.hidden = false; }
    });
})();
</script>
