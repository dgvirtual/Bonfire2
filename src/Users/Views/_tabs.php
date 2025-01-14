<ul class="nav nav-tabs nav-fill" style="margin-bottom: -2px;">
    <li class="nav-item">
        <a class="nav-link <?php if ($tab === 'details') : ?> active <?php endif ?>"
           href="<?= isset($user) ? $user->adminLink() : '#' ?>">
            <?= lang('Users.cardDetails') ?>
        </a>
    </li>
    <?php if (isset($user) && $user !== null) : ?>
        <?php if (auth()->user()->can('users.edit')) : ?>
            <li class="nav-item">
                <a class="nav-link <?php if ($tab === 'permissions') : ?> active <?php endif ?>"
                   href="<?= $user->adminLink('permissions') ?>">
                    <?= lang('Users.cardPermissions') ?>
                </a>
            </li>
        <?php endif ?>
    <?php endif ?>
    <li class="nav-item">
        <?php if (isset($user) && $user !== null) : ?>
            <a class="nav-link <?php if ($tab === 'security') : ?> active <?php endif ?>"
               href="<?= $user->adminLink('security') ?>">
                <?= lang('Users.cardSecurity') ?>
            </a>
        <?php endif ?>
    </li>
    <?= service('resourceTabs')->renderTabsFor('user') ?>
</ul>
