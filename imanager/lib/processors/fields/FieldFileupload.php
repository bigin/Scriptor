<?php namespace Imanager;

class FieldFileupload implements FieldInterface
{
	/**
	 * @var ItemManager
	 */
	protected $imanager;

	/**
	 * @var null|string - Real field name
	 */
	public $name = null;

	/**
	 * @var null|string - URL to the root of the upload handler
	 */
	public $action = null; // '../plugins/imanager/upload/server/php/'

	/**
	 * @var null|string - URL to the IManager root
	 */
	public $url = null; // '../plugins/imanager/upload/server/js/'

	/**
	 * @var null|string - URL to the folder where you have stored your static resources
	 */
	public $jsurl = null; // '../plugins/imanager/upload/server/js/'

	/**
	 * @var null|string - CSS-Class of the field
	 */
	public $class = null;

	/**
	 * @var null|string - CSS-ID of the field
	 */
	public $id = null;

	/**
	 * @var null|int - Real field id
	 */
	public $fieldid = null;

	/**
	 * @var FieldConfigs|null
	 */
	public $configs = null;

	/**
	 * @var null|mixed - Field value
	 */
	public $value = null;

	/**
	 * @var null|int - Category id
	 */
	public $categoryid = null;

	/**
	 * @var null|int - Item id
	 */
	public $itemid = null;

	/**
	 * @var null|int - Current timestamp
	 */
	public $timestamp = null;

	/**
	 * @var array Default configs
	 */
	protected $defaults = array(
		'accept_types' => 'gif|jpe?g|png'
	);

	public $labels = array(
		'add_files' => 'Add Files',
		'start' => 'Upload',
		'cancel' => 'Cancel',
		'delete' => 'Remove',
		'placeholder' => 'Enter an image title',
	);

	/**
	 * FieldFileupload constructor
	 */
	public function __construct()
	{
		$this->imanager = imanager();
		$this->setDefaults();
	}

	protected function setDefaults()
	{
		if(!isset($this->configs)) { $this->configs = new FieldConfigs();}
		foreach($this->defaults as $key => $value) {
			if(!isset($this->configs->{$key})) { $this->configs->{$key} = $value; }
		}
	}

	public function set($name, $value, $sanitize = true) {
		$this->{strtolower($name)} = ($sanitize) ? $this->imanager->sanitizer->text($value) : $value;
	}

	public function render()
	{
		$siteUrl = rawurlencode($this->url);
		$this->jsurl = $this->url.'imanager/upload/js/';
		$urlParams = "itemid={$this->itemid}&categoryid={$this->categoryid}&fieldid={$this->
			fieldid}&timestamp={$this->timestamp}&siteurl={$siteUrl}";

		return $this->imanager->templateParser->render('fields/fileupload', array(
				'action' => $this->action,
				'add_files' => $this->labels['add_files'],
				'start_upload' => $this->labels['start'],
				'cancel_upload' => $this->labels['cancel'],
				'delete_upload' => $this->labels['delete'],
				'imagetitle_placeholder' => $this->labels['placeholder'],
				'jsurl' => $this->jsurl,
				'scripturl' => "{$this->action}?{$urlParams}",
				'deleteurl' => "&{$urlParams}",
				'thumb_width' => $this->imanager->config->thumbSize['width'],
				'thumb_height' => $this->imanager->config->thumbSize['height'],
				'name' => $this->name,
				'class' => $this->class,
				'id' => $this->id,
				'value' => $this->value,
				'itemid' => $this->itemid,
				'categoryid' => $this->categoryid,
				'fieldid' => $this->fieldid,
				'timestamp' => $this->timestamp,
			), array(), true
		);
	}

	public function renderJsLibs()
	{
		$this->jsurl = $this->url.'imanager/upload/js/';
		ob_start(); ?>
		<!-- The jQuery UI widget factory, can be omitted if jQuery UI is already included -->
		<script src="<?php echo $this->jsurl; ?>jquery.ui.widget.js"></script>
		<!-- The Templates plugin is included to render the upload/download listings -->
		<script src="<?php echo $this->jsurl; ?>tmpl.min.js"></script>
		<!-- The Load Image plugin is included for the preview images and image resizing functionality -->
		<script src="<?php echo $this->jsurl; ?>load-image.all.min.js"></script>
		<!-- The Canvas to Blob plugin is included for image resizing functionality -->
		<script src="<?php echo $this->jsurl; ?>canvas-to-blob.min.js"></script>
		<script src="<?php echo $this->jsurl; ?>bootstrap.min.js"></script>
		<!-- blueimp Gallery script -->
		<script src="<?php echo $this->jsurl; ?>jquery.blueimp-gallery.min.js"></script>
		<!-- The Iframe Transport is required for browsers without support for XHR file uploads -->
		<script src="<?php echo $this->jsurl; ?>jquery.iframe-transport.js"></script>
		<!-- The basic File Upload plugin -->
		<script src="<?php echo $this->jsurl; ?>jquery.fileupload.js"></script>
		<!-- The File Upload processing plugin -->
		<script src="<?php echo $this->jsurl; ?>jquery.fileupload-process.js"></script>
		<!-- The File Upload image preview & resize plugin -->
		<script src="<?php echo $this->jsurl; ?>jquery.fileupload-image.js"></script>
		<!-- The File Upload audio preview plugin -->
		<script src="<?php echo $this->jsurl; ?>jquery.fileupload-audio.js"></script>
		<!-- The File Upload video preview plugin -->
		<script src="<?php echo $this->jsurl; ?>jquery.fileupload-video.js"></script>
		<!-- The File Upload validation plugin -->
		<script src="<?php echo $this->jsurl; ?>jquery.fileupload-validate.js"></script>
		<!-- The File Upload user interface plugin -->
		<script src="<?php echo $this->jsurl; ?>jquery.fileupload-ui.js"></script>
		<script src="<?php echo $this->jsurl; ?>jquery.fileupload-jquery-ui.js"></script>
		<script src="<?php echo $this->jsurl; ?>field.actions.js"></script>
		<?php return ob_get_clean();
	}

	public function renderJsBlock()
	{
		$siteUrl = rawurlencode($this->url);
		$urlParams = "itemid={$this->itemid}&categoryid={$this->categoryid}&fieldid={$this->
			fieldid}&timestamp={$this->timestamp}&siteurl={$siteUrl}";
		$block =
		'<script>
			$(function() {
			"use strict";
			// Initialize the jQuery File Upload widget:
			$("#fileupload_'.$this->id.'").fileupload({
				// Uncomment the following to send cross-domain cookies:
				url: "'.$this->action.'?'.$urlParams.'",
				uploadTemplateId: "template-upload_'.$this->id.'",
				downloadTemplateId: "template-download_'.$this->id.'",
			},
			"option",
			{
				previewMaxWidth: '.$this->imanager->config->thumbSize['width'].',
				previewMaxHeight: '.$this->imanager->config->thumbSize['height'].'
			},
			"redirect",
				window.location.href.replace(
					/\/[^\/]*$/,
					"/cors/result.html?%s"
				)
			);
			$("#fileupload_'.$this->id.'").addClass("fileupload-processing");
			$.ajax({
				url: $("#fileupload_'.$this->id.'").fileupload("option", "url"),
				dataType: "json",
				context: $("#fileupload_'.$this->id.'")[0]
			}).always(function (result) {
				//console.log(result);
				$("#fileupload_'.$this->id.'").removeClass("fileupload-processing");
			}).done(function (result) {
				//console.log(result);
			$(this).fileupload("option", "done")
				.call(this, $.Event("done"), {result: result});
			});
			// Load existing files:
			$(".table tbody").sortable({
				items:"tr.sortable", handle:"td",
				update:function(e,ui) {
					renumberImages();
				}
			});
			renumberImages();
		});
		</script>';
		return $block;
	}

	public function getConfigFieldtype()
	{
		/* ok, get our dropdown field, infotext and area templates */
		$tpltext = $this->tpl->getTemplate('text', $this->tpl->getTemplates('field'));
		$tplinfotext = $this->tpl->getTemplate('infotext', $this->tpl->getTemplates('itemeditor'));
		$tplarea = $this->tpl->getTemplate('fieldarea', $this->tpl->getTemplates('itemeditor'));
		// let's load accepted value
		$accept_types = isset($this->configs->accept_types) ? $this->configs->accept_types : '';
		// render textfied <input name="[[name]]" type="text" class="[[class]]" id="[[id]]" value="[[value]]"[[style]]/>
		$textfied = $this->tpl->render($tpltext, array(
				// NOTE: The CUSTOM_PREFIX must always be used as a part of the field name
				'name' => self::CUSTOM_PREFIX.'accept_types',
				'class' => '',
				'id' => '',
				'value' => $accept_types
			)
		);
		// render infotext template <p class="field-info">[[infotext]]</p>
		$infotext = $this->tpl->render($tplinfotext, array(
				'infotext' => '<i class="fa fa-info-circle"></i>
					Accepted file types separated by pipe, example: gif|jpe?g|png|pdf')
		);
		// let's merge the pieces and return the output
		return $this->tpl->render($tplarea, array(
				'fieldid' =>  '',
				'label' => 'Enter accepted file types here',
				'infotext' => $infotext,
				'area-class' => 'fieldarea',
				'label-class' => '',
				'required' => '',
				'field' => $textfied), true
		);
	}
}
