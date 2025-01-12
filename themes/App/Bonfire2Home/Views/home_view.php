<?php $this->extend('master') ?>

<?php $this->section('styles') ?>
<?php $this->endSection() ?>

<?php $this->section('navbar') ?>
<?= $this->include('App\Modules\Bonfire2Home\Views\_navbar'); ?>
<?php $this->endSection() ?>

<?php $this->section('main') ?>
<div class="row">
    <!-- Main Content -->
    <?= $this->include('App\Modules\Bonfire2Home\Views\_content'); ?>
    <!-- Sidebar -->
    <div class="col-md-3">
        <?= view_cell('TakeActionCell') ?>
        <!-- controlled component example -->
        <x-famous-quotes seconds="29" />
        <?= view_cell('ArticlesCell') ?>
        <?= view_cell('UsersCell::render', 'limit=8') ?>
        <?= view_cell('BonfireContributorsCell') ?>
    </div>
</div>
<?php $this->endSection() ?>