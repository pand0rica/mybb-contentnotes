# Changelog

All notable changes to this project are documented in this file. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [1.0.0] - 2026-11-06

### New
- Content note categories in the ACP (Configuration → Content Notes): name, short code, description, active/inactive, display
  order; activate/deactivate/delete with confirmation; entries in the admin log.
- Content notes on **whole threads**, **single posts** and **single passages** (`[cw=short-code|hint]…[/cw]`), several
  categories at once, each with its own optional hint (max. 255 characters); form in *New Thread*, *New Reply*, quick reply and
  *Edit Post*; editor button with category dropdown (WYSIWYG round trip).
- Per-member setting in the User CP, per category: show a warning first (default), show directly, or **hide completely**.
  The strictest setting wins; guests always get the warning; the preview never hides.
- Thread badges in the forum view and in search results, a warning box and hidden posts in the thread, a collapsed warning for
  single posts, inline label with blurred text for passages. Hidden threads/posts are removed from the finished page; a hidden
  thread opened directly shows a notice.
- Settings: enable, allowed groups, forums, editor button. Plugin data survives deactivating and reactivating.
- English and German (du-form) language files, separated into frontend and admin sets.
- Public API (`contentnotes_get_notes()`, `contentnotes_viewer_action()`, …) and hook `contentnotes_notes_saved`.
- CLI lifecycle helper and self-test (`tests/`).

### Security
- All writing ACP actions run only under POST (the ACP checks the post key); no custom key check, no state-changing links.
- Hint texts are cleaned (tags stripped, escaped) and the `[cw]` parser works with and without HTML allowed in posts.
- The markers used to remove hidden threads and posts carry a per-request secret, so a post with allowed HTML cannot make other
  content disappear.
