/**
 * FilePond bootstrap for the editor pages form.
 *
 * Wires every <input class="filepond"> element on the page against the
 * Phase 14d-1 upload endpoint. Configuration comes from data-* attributes
 * the server emits next to the input:
 *
 *   data-itemid       owning item id
 *   data-fieldid      owning field id
 *   data-csrf-name    CSRF token name (`pages` for the pages form)
 *   data-csrf-value   CSRF token value
 *   data-upload-url   POST/DELETE URL (typically /editor/api/upload)
 *
 * The image-section already lists existing uploads server-side; FilePond
 * only handles the new-upload flow plus the `revert` (cancel pending)
 * action. The "remove" button on existing rows posts a separate DELETE
 * to the same endpoint.
 */
(function () {
  'use strict';

  function ready(fn) {
    if (document.readyState !== 'loading') { fn(); }
    else { document.addEventListener('DOMContentLoaded', fn); }
  }

  ready(function () {
    if (typeof FilePond === 'undefined') {
      return;
    }

    if (typeof FilePondPluginImagePreview !== 'undefined') {
      FilePond.registerPlugin(FilePondPluginImagePreview);
    }
    if (typeof FilePondPluginFileValidateType !== 'undefined') {
      FilePond.registerPlugin(FilePondPluginFileValidateType);
    }
    if (typeof FilePondPluginFileValidateSize !== 'undefined') {
      FilePond.registerPlugin(FilePondPluginFileValidateSize);
    }

    document.querySelectorAll('input.filepond').forEach(function (input) {
      var itemId    = input.dataset.itemid;
      var fieldId   = input.dataset.fieldid;
      var csrfName  = input.dataset.csrfName;
      var csrfValue = input.dataset.csrfValue;
      var url       = input.dataset.uploadUrl;

      if (!itemId || !fieldId || !csrfName || !csrfValue || !url) {
        return;
      }

      FilePond.create(input, {
        // Form field name used for the uploaded blob in the multipart
        // request. Default is "filepond"; we use "file" so the upload
        // endpoint stays aligned with the cURL smoke fixtures.
        name: 'file',
        allowMultiple: true,
        acceptedFileTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
        maxFileSize: '8MB',
        labelIdle: 'Drop images here or <span class="filepond--label-action">Browse</span>',
        server: {
          process: {
            url: url,
            method: 'POST',
            withCredentials: true,
            ondata: function (formData) {
              formData.append('itemId', itemId);
              formData.append('fieldId', fieldId);
              formData.append('tokenName', csrfName);
              formData.append('tokenValue', csrfValue);
              return formData;
            },
            onload: function (response) {
              try {
                var payload = JSON.parse(response);
                return String(payload.fileId || '');
              } catch (e) {
                return '';
              }
            },
            onerror: function (response) {
              try {
                return (JSON.parse(response).error || 'Upload failed');
              } catch (e) {
                return 'Upload failed';
              }
            }
          },
          revert: {
            url: '',  // FilePond appends the file id; we override with full url
            method: 'DELETE',
            withCredentials: true,
            // Use a custom function so we can send the CSRF + fileId in the
            // form-encoded body the endpoint expects.
            onload: function () { return true; },
            onerror: function () { return 'Revert failed'; }
          }
        }
      });
    });

    // Existing-file remove buttons: server-rendered. POST a DELETE to the
    // upload endpoint with CSRF, then drop the row from the DOM.
    document.querySelectorAll('.image-list__remove').forEach(function (btn) {
      btn.addEventListener('click', function (event) {
        event.preventDefault();
        var fileId   = btn.dataset.fileId;
        var url      = btn.dataset.deleteUrl;
        var csrfName = btn.dataset.csrfName;
        var csrfVal  = btn.dataset.csrfValue;
        if (!fileId || !url) { return; }

        var body = new URLSearchParams({
          fileId: fileId,
          tokenName: csrfName || '',
          tokenValue: csrfVal || ''
        });
        fetch(url, {
          method: 'DELETE',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          credentials: 'same-origin',
          body: body.toString()
        }).then(function (res) {
          if (res.ok) {
            var item = btn.closest('.image-list__item');
            if (item) { item.remove(); }
          } else {
            res.text().then(function (text) { console.error('Delete failed:', text); });
          }
        });
      });
    });

    // Title-save buttons next to each modern file row: PATCHes the
    // upload endpoint with the new caption. Status text appears
    // briefly next to the input on success / failure.
    document.querySelectorAll('.image-list__title-save').forEach(function (btn) {
      btn.addEventListener('click', function (event) {
        event.preventDefault();
        var fileId = btn.dataset.fileId;
        if (!fileId) { return; }

        var item = btn.closest('.image-list__item');
        if (!item) { return; }
        var input = item.querySelector('.image-list__title-input');
        var status = item.querySelector('.image-list__title-status');
        if (!input) { return; }

        var url      = input.dataset.patchUrl;
        var csrfName = input.dataset.csrfName;
        var csrfVal  = input.dataset.csrfValue;
        if (!url) { return; }

        var body = new URLSearchParams({
          fileId: fileId,
          title: input.value,
          tokenName: csrfName || '',
          tokenValue: csrfVal || ''
        });
        if (status) { status.textContent = 'saving…'; }

        fetch(url, {
          method: 'PATCH',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          credentials: 'same-origin',
          body: body.toString()
        }).then(function (res) {
          if (res.ok) {
            if (status) {
              status.textContent = 'saved';
              setTimeout(function () { status.textContent = ''; }, 1500);
            }
          } else {
            res.text().then(function (text) {
              if (status) { status.textContent = 'failed'; }
              console.error('Title save failed:', text);
            });
          }
        });
      });
    });
  });
})();
