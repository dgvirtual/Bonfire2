<?php use Bonfire\Users\User;
$this->extend('master') ?>

<?php $this->section('main') ?>
<x-page-head>
    <a href="<?= site_url(ADMIN_AREA . '/users') ?>" class="back">
        <i class="fa fa-arrow-left"></i>
        <?= lang('Users.usersModTitle') ?>
    </a>
    <h2><?= isset($user) ? lang('Users.editUser') : lang('Users.newUser') ?></h2>
</x-page-head>

<?php if (isset($user) && $user->deleted_at !== null) : ?>
    <div class="alert danger">
        <?= lang('Users.userDeletedOn', [$user->deleted_at->humanize()]) ?>.
        <a href="#"><?= lang('Users.restoreUser') ?>?</a>
    </div>
<?php endif ?>

<?= view('Bonfire\Users\Views\_tabs', ['tab' => 'details', 'user' => $user ?? null]) ?>

<x-admin-box>

    <?php if (isset($user) && $user !== null) : ?>
        <form action="<?= $user->adminLink('/save') ?>" method="post" enctype="multipart/form-data">
        <?php else : ?>
            <form action="<?= (new User())->adminLink('/save') ?>" method="post" enctype="multipart/form-data">
            <?php endif ?>
            <?= csrf_field() ?>

            <?php if (isset($user) && $user !== null) : ?>
                <input type="hidden" name="id" value="<?= $user->id ?>">
            <?php endif ?>

            <fieldset class="first">
                <legend><?= lang('Users.basicInfo') ?></legend>

                <div class="row">
                    <div id="avatar-place" class="col-12 col-sm-3 d-flex align-items-top pt-3">
                        <!-- Avatar preview and edit links -->
                        <?= $this->include('\Bonfire\Users\Views\_avatar') ?>
                    </div>
                    <div class="col">
                        <div class="row">
                            <!-- Email Address -->
                            <div class="form-group col-12 col-sm-6">
                                <label for="email" class="form-label"><?= lang('Users.email') ?></label>
                                <div class="input-group">
                                    <span class="input-group-text" id="email">@</span>
                                    <input type="text" name="email" class="form-control" autocomplete="email" value="<?= old('email', $user->email ?? '') ?>">
                                </div>
                                <?php if (has_error('email')) : ?>
                                    <p class="text-danger"><?= error('email') ?></p>
                                <?php endif ?>
                            </div>
                            <!-- Username -->
                            <div class="form-group col-12 col-sm-6">
                                <label for="username" class="form-label"><?= lang('Users.username') ?></label>
                                <input type="text" name="username" class="form-control" autocomplete="username" value="<?= old('username', $user->username ?? '') ?>">
                                <?php if (has_error('username')) : ?>
                                    <p class="text-danger"><?= error('username') ?></p>
                                <?php endif ?>
                            </div>
                        </div>

                        <div class="row">
                            <!-- First Name -->
                            <div class="form-group col-12 col-sm-6">
                                <label for="first_name" class="form-label"><?= lang('Users.firstName') ?></label>
                                <input type="text" name="first_name" class="form-control" autocomplete="first_name" value="<?= old('first_name', $user->first_name ?? '') ?>">
                                <?php if (has_error('first_name')) : ?>
                                    <p class="text-danger"><?= error('first_name') ?></p>
                                <?php endif ?>
                            </div>
                            <!-- Last Name -->
                            <div class="form-group col-12 col-sm-6">
                                <label for="last_name" class="form-label"><?= lang('Users.lastName') ?></label>
                                <input type="text" name="last_name" class="form-control" autocomplete="last_name" value="<?= old('last_name', $user->last_name ?? '') ?>">
                                <?php if (has_error('last_name')) : ?>
                                    <p class="text-danger"><?= error('last_name') ?></p>
                                <?php endif ?>
                            </div>
                        </div>
                    </div>
                </div>
            </fieldset>
            <?php if (isset($user) && $user->id !== null) : ?>
            <fieldset>
                <legend><?= lang('Users.status') ?></legend>
                <div
                    x-data="{ isChecked: <?= $user->isBanned() ? 'true' : 'false' ?> }">
                    <input class="form-check-input" type="checkbox" name="activate" id="activate" value="1"
                        <?php if (! $user->isNotActivated()) : ?>
                            checked disabled
                        <?php endif; ?>
                    >
                    <label class="form-check-label" for="activate">
                    <?= lang('Users.activated') ?>
                    </label><br>
                    <input type="hidden" name="ban" value="0">
                    <input class="form-check-input" type="checkbox" name="ban" id="ban" value="1" x-model="isChecked"
                        <?php if (
                            $itsMe
                            || (
                                ! auth()->user()->can('users.manage-admins')
                                && $user->inGroup('admin', 'superadmin')
                            )
                        ) : ?>
                    disabled
                    <?php endif; ?>
                    >
                    <label class="form-check-label" for="ban">
                    <?= lang('Users.banned') ?><span class="x-cloak fw-bold" x-show="isChecked">, <?= lang('Users.enterBanReason') ?></span>
                    </label>
                    <input x-show="isChecked" x-bind:disabled="!isChecked" type="text" name="ban_reason" id="ban_reason"
                        class="form-control form-control-sm x-cloak"
                        value="<?= $user->getBanMessage() ?>">
                </div>
            </fieldset>
            <?php endif; ?>

            <fieldset>
                <legend><?= lang('Users.groups') ?></legend>

                <?php if (auth()->user()->can('users.edit')) : ?>
                <p><?= lang('Users.selectGroups') ?>
                    <?php if(! auth()->user()->can('users.manage-admins')) : ?>
                        <?= lang('Users.cannotAddAdminGroups') ?>.
                    <?php endif; ?>
                </p>
                <?php else : ?>
                    <p><?= lang('Users.groupListDisabled') ?>.</p>
                <?php endif; ?>

                <div class="row">
                    <div class="form-group col-12 col-sm-6">
                        <select name="groups[]" multiple="multiple" class="form-control"
                            <?php if (! auth()->user()->can('users.edit')) {
                                echo ' disabled';
                            } ?>>
                            <?php foreach ($groups as $group => $info) : ?>
                                <option value="<?= $group ?>"
                                    <?php
                                    // Check if the form was submitted and validation failed
                                    $oldGroups = old('groups', isset($user) ? $user->getGroups() : []);
                                    if (in_array($group, $oldGroups)) : ?>
                                        selected
                                    <?php endif ?>
                                    <?php if (
                                        ! auth()->user()->can('users.manage-admins')
                                        && in_array($group, ['admin','superadmin'])
                                    ) : ?> disabled
                                    <?php endif ?>
                                >
                                    <?= $info['title'] ?? $group ?>
                                </option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>
            </fieldset>

            <!-- User Meta Fields -->
                <?= view_cell('\Bonfire\Users\Libraries\UserCells::metaFormFields') ?>

            <x-button-container>
                    <x-button><?= lang('Users.saveUser') ?></x-button>
            </x-button-container>

            </form>

</x-admin-box>

<?php $this->endSection() ?>
