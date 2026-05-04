/**
 * FilePond bootstrap for the editor pages form.
 *
 * Wires every <input class="filepond"> element on the page against the
 * Phase 14d-1 upload endpoint. Configuration comes from data-* attributes
 * the server emits next to the input:
 *
 *   data-itemid       owning item id (0 = new page; deferred mode)
 *   data-fieldid      owning field id
 *   data-csrf-name    CSRF token name (`pages` for the pages form)
 *   data-csrf-value   CSRF token value
 *   data-upload-url   POST/DELETE URL (typically /editor/api/upload)
 *
 * Two operating modes:
 *   - itemId > 0 (existing page): files upload immediately when added.
 *   - itemId = 0 (new page): files stage in memory until form submit.
 *     The submit handler POSTs the page form via fetch with
 *     `X-Requested-With: XMLHttpRequest`, reads the new pageId from the
 *     JSON response, then runs `processFiles()` so each staged file
 *     uploads against the freshly created page. Once every upload
 *     resolves, the browser navigates to the redirect URL.
 *
 * Image titles (captions) live on the page form as
 * `image_titles[<fileId>]` inputs and save with the page — there is no
 * per-image XHR. The "remove" button on existing rows posts a
 * dedicated DELETE to the upload endpoint.
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
      var widget = {
        itemId:    input.dataset.itemid || '0',
        fieldId:   input.dataset.fieldid,
        csrfName:  input.dataset.csrfName,
        csrfValue: input.dataset.csrfValue,
        url:       input.dataset.uploadUrl
      };

      if (!widget.fieldId || !widget.csrfName || !widget.csrfValue || !widget.url) {
        return;
      }

      var deferred = (widget.itemId === '0');

      var pond = FilePond.create(input, {
        // Form field name used for the uploaded blob in the multipart
        // request. Default is "filepond"; we use "file" so the upload
        // endpoint stays aligned with the cURL smoke fixtures.
        name: 'file',
        allowMultiple: true,
        instantUpload: !deferred,
        acceptedFileTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
        maxFileSize: '8MB',
        labelIdle: 'Drop images here or <span class="filepond--label-action">Browse</span>',
        server: {
          process: {
            url: widget.url,
            method: 'POST',
            withCredentials: true,
            ondata: function (formData) {
              // widget.itemId is read live so the deferred-mode handler
              // below can swap in the freshly created page id before
              // triggering processFiles().
              formData.append('itemId', widget.itemId);
              formData.append('fieldId', widget.fieldId);
              formData.append('tokenName', widget.csrfName);
              formData.append('tokenValue', widget.csrfValue);
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
            url: '',
            method: 'DELETE',
            withCredentials: true,
            onload: function () { return true; },
            onerror: function () { return 'Revert failed'; }
          }
        }
      });

      if (deferred) {
        attachDeferredSubmit(input, pond, widget);
      }
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
  });

  /**
   * Hook the parent <form>'s submit so a new page can be saved first
   * (XHR returns the new pageId), then each staged file uploads against
   * that id, then we navigate to the redirect URL the server returned.
   *
   * If FilePond has no files staged we fall through to the normal
   * synchronous form submit — there is nothing to coordinate.
   */
  function attachDeferredSubmit(input, pond, widget) {
    var form = input.closest('form');
    if (!form) { return; }

    form.addEventListener('submit', function (event) {
      if (pond.getFiles().length === 0) { return; }
      event.preventDefault();

      var formData = new FormData(form);
      var redirect = null;

      fetch(form.action || window.location.href, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        credentials: 'same-origin',
        body: formData
      }).then(function (res) {
        return res.json().then(function (data) { return { ok: res.ok, data: data }; });
      }).then(function (result) {
        if (!result.ok || !result.data || !result.data.pageId) {
          throw new Error((result.data && result.data.error) || 'Save failed');
        }
        widget.itemId = String(result.data.pageId);
        redirect = result.data.redirect;
        return pond.processFiles();
      }).then(function () {
        window.location = redirect || (form.action || '/editor/pages/');
      }).catch(function (err) {
        console.error(err);
        alert('Save failed: ' + (err && err.message ? err.message : err));
      });
    });
  }
})();
