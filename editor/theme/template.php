<?php
declare(strict_types=1);
isset($editor) or die('You cannot access this page directly');
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title><?php echo $editor->pageTitle; ?></title>
	<meta name="description" content="">
	<!-- Mobile-friendly viewport -->
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="icon" href="<?php echo $editor->assetUrl('favicon.ico'); ?>" type="image/x-icon" />
	<link rel="stylesheet" href="<?php echo $editor->assetUrl('css/prism.css'); ?>">
	<link href="<?php echo $editor->assetUrl('css/css-gg.min.css'); ?>" rel="stylesheet">
	<link rel="stylesheet" href="<?php echo $editor->assetUrl('css/jquery-ui.css'); ?>">
	<link rel="stylesheet" href="<?php echo $editor->assetUrl('css/tokens.css'); ?>">
	<link rel="stylesheet" href="<?php echo $editor->assetUrl('css/styles.css'); ?>">
	<link rel="stylesheet" href="<?php echo $editor->assetUrl('scripts/filepond/filepond.css'); ?>">
	<link rel="stylesheet" href="<?php echo $editor->assetUrl('scripts/filepond/filepond-image-preview.css'); ?>">
	<?php echo $editor->getResources('link'); ?>
	<script src="<?php echo $editor->assetUrl('scripts/jquery.min.js'); ?>"></script>
	<script src="<?php echo $editor->assetUrl('scripts/jquery-ui.min.js'); ?>"></script>
	<?php echo $editor->getResources('script'); ?>
</head>
<body>
<div id="delay"><div id="clamp"><span id="loader"></span></div></div>
<main role="main">
	<?php include 'summary.php'; ?>
	<?php include 'header.php'; ?>
	<div class="page-wrapper">
		<div class="page">
			<div class="page-inner arounder">
				<?php echo $editor->getProperty('messages'); ?>
				<?php echo $editor->getProperty('pageContent'); ?>
			</div>
		</div>
	</div>
	<footer role="contentinfo">
		<div>
			<a href="https://scriptor-cms.info">Scriptor</a> <?php echo htmlspecialchars($editor->version, ENT_QUOTES); ?> &copy; <time datetime="<?php echo date('Y'); ?>"><?php echo date('Y'); ?></time>
		</div>
	</footer>
</main>
<?php echo $editor->getProperty('jsConfig'); ?>
<script src="<?php echo $editor->assetUrl('scripts/remarkable/remarkable.min.js'); ?>"></script>
<script src="<?php echo $editor->assetUrl('scripts/prism.js'); ?>"></script>
<script src="<?php echo $editor->assetUrl('scripts/editor.js'); ?>"></script>
<script src="<?php echo $editor->assetUrl('scripts/filepond/filepond.js'); ?>"></script>
<script src="<?php echo $editor->assetUrl('scripts/filepond/filepond-image-preview.js'); ?>"></script>
<script src="<?php echo $editor->assetUrl('scripts/filepond/filepond-file-validate-type.js'); ?>"></script>
<script src="<?php echo $editor->assetUrl('scripts/filepond/filepond-file-validate-size.js'); ?>"></script>
<script src="<?php echo $editor->assetUrl('scripts/filepond-init.js'); ?>"></script>
<?php echo $editor->getResources('script', 'boddy'); ?>
</body>
</html>
