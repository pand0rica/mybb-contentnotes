/**
 * Content Notes – Aufdecken von Inhaltswarnungen und SCEditor-Button für [cw].
 *
 * Reines ES5, kein Build. Ohne JavaScript bleibt jeder Inhalt lesbar (die Verdeckung greift
 * erst, wenn dieses Script die Klasse "contentnotes_js" am <html>-Element gesetzt hat).
 */
(function () {
	'use strict';

	// Verdecken und Aufdecken nur mit JS möglich: Flag sofort setzen (Script steht im <head>)
	document.documentElement.className += ' contentnotes_js';

	function closest(node, selector) {
		while (node && node.nodeType === 1) {
			if ((node.matches || node.msMatchesSelector).call(node, selector)) {
				return node;
			}
			node = node.parentNode;
		}
		return null;
	}

	/** Deckt einen Thread- bzw. Post-Block auf (Button im Warnkasten). */
	function revealBlock(button) {
		var targetId = button.getAttribute('data-contentnotes-target');
		var target = null;

		if (targetId) {
			target = document.getElementById(targetId);
		} else {
			var wrap = closest(button, '.contentnotes_wrap');
			if (wrap) {
				target = wrap.querySelector('.contentnotes_veiled');
			}
		}

		if (target) {
			target.className = target.className.replace(/(^|\s)contentnotes_veiled(?=\s|$)/g, ' ').replace(/^\s+|\s+$/g, '');
		}
		button.setAttribute('aria-expanded', 'true');
		button.style.display = 'none';
	}

	/** Deckt eine einzelne Textstelle auf. */
	function revealInline(element) {
		if (/(^|\s)is-revealed(\s|$)/.test(element.className)) {
			return;
		}
		element.className += ' is-revealed';
		element.setAttribute('aria-expanded', 'true');

		var text = element.querySelector('.contentnotes_inline_text');
		if (text) {
			text.removeAttribute('aria-hidden');
		}
	}

	document.addEventListener('click', function (event) {
		var button = closest(event.target, '.contentnotes_reveal');
		if (button) {
			revealBlock(button);
			return;
		}

		var inline = closest(event.target, '.contentnotes_inline');
		if (inline) {
			revealInline(inline);
		}
	});

	document.addEventListener('keydown', function (event) {
		var key = event.key;
		if (key !== 'Enter' && key !== ' ' && key !== 'Spacebar') {
			return;
		}

		var inline = closest(event.target, '.contentnotes_inline');
		if (inline && inline === event.target) {
			event.preventDefault();
			revealInline(inline);
		}
	});

	// Verdeckten Text für Screenreader ausblenden, bis er aufgedeckt wird
	document.addEventListener('DOMContentLoaded', function () {
		var texts = document.querySelectorAll('.contentnotes_inline:not(.is-revealed) .contentnotes_inline_text');
		for (var i = 0; i < texts.length; i++) {
			texts[i].setAttribute('aria-hidden', 'true');
		}
	});

	// ---------------------------------------------------------------------------------------
	// SCEditor: Button „Inhaltswarnung“ und BBCode-Format [cw=kuerzel|Hinweis]…[/cw]
	// ---------------------------------------------------------------------------------------
	if (!window.jQuery) {
		return;
	}

	jQuery(function ($) {
		var config = window.contentnotes_config;

		// Nur für berechtigte Gruppen; Konfiguration liefert der Header (contentnotes_config)
		if (!config || !config.canSet || typeof window.opt_editor === 'undefined' || !$.sceditor) {
			return;
		}

		var lang = config.lang || {};
		var categories = config.categories || [];

		function escapeAttr(value) {
			return String(value)
				.replace(/&/g, '&amp;')
				.replace(/"/g, '&quot;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;');
		}

		// WYSIWYG-Roundtrip: <span data-cw="kuerzel" data-cwnote="Hinweis"> <-> [cw=kuerzel|Hinweis]
		$.sceditor.formats.bbcode.set('cw', {
			tags: {
				span: {
					'data-cw': null
				}
			},
			isInline: true,
			format: function (element, content) {
				var slug = $(element).attr('data-cw') || '';
				var note = $(element).attr('data-cwnote') || '';
				var attr = '';

				if (slug) {
					attr = '=' + slug + (note ? '|' + note : '');
				}

				return '[cw' + attr + ']' + content + '[/cw]';
			},
			html: function (token, attrs, content) {
				var parts = String(attrs.defaultattr || '').split('|');
				var slug = parts.shift() || '';
				var note = parts.join('|');

				return '<span data-cw="' + escapeAttr(slug) + '" data-cwnote="' + escapeAttr(note) + '"'
					+ ' style="background: rgba(255, 193, 7, 0.25); border-bottom: 2px dotted #a07800;">'
					+ content + '</span>';
			}
		});

		$.sceditor.command.set('contentnotes', {
			_dropDown: function (editor, caller) {
				var $content = $('<div class="contentnotes_dropdown"></div>');

				if (!categories.length) {
					$content.text(lang.none || '');
					editor.createDropDown(caller, 'insertcontentnote', $content.get(0));
					return;
				}

				var $select = $('<select id="contentnotes_editor_category"></select>');
				$.each(categories, function (index, category) {
					$select.append($('<option></option>').val(category.slug).text(category.name));
				});

				var $note = $('<input type="text" id="contentnotes_editor_note" class="textbox" maxlength="255" />');
				var $button = $('<input type="button" class="button" />').val(lang.insert || 'Insert');

				$content
					.append($('<div></div>')
						.append($('<label for="contentnotes_editor_category"></label>').text((lang.category || 'Category') + ': '))
						.append($select))
					.append($('<div></div>')
						.append($('<label for="contentnotes_editor_note"></label>').text((lang.note || 'Note') + ': '))
						.append($note))
					.append($('<div></div>').append($button));

				$button.on('click', function (event) {
					var note = $.trim($note.val()).replace(/[\]\r\n]+/g, ' ');

					editor.insert('[cw=' + $select.val() + (note ? '|' + note : '') + ']', '[/cw]');
					editor.closeDropDown(true);
					event.preventDefault();
				});

				editor.createDropDown(caller, 'insertcontentnote', $content.get(0));
			},
			exec: function (caller) {
				$.sceditor.command.get('contentnotes')._dropDown(this, caller);
			},
			txtExec: function (caller) {
				$.sceditor.command.get('contentnotes')._dropDown(this, caller);
			},
			tooltip: lang.button || 'Content note'
		});

		// Button in die Toolbar legen (hinter „quote“), ohne das Core-Template zu ändern
		var toolbar = String(window.opt_editor.toolbar || '');
		if (toolbar.indexOf('contentnotes') === -1) {
			if (toolbar.indexOf('quote|') !== -1) {
				toolbar = toolbar.replace('quote|', 'quote,contentnotes|');
			} else {
				toolbar += '|contentnotes';
			}
			window.opt_editor.toolbar = toolbar;
		}
	});
}());
