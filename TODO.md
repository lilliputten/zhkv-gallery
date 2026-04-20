`thumb.php` should to check/create thumb cache and return a redirect to it.

- `view.php` should allow slighly overscale for the images, over the original image width.

- Use LQIP thumbnails in pages:

Extract thumbnail image generating function to the `helpers.php`.
It should check if the thumnbnail is already exists and generate it in case if not.
It should be able to use the different image types: the same as specified by the original image format, or specified with the config parameter (`imageFormat`) or by passed parameter (with the same name).
It should accept the image file name, preview mode and preview size.
Should return a dataset which includes thumnail file name.
Use it in the `view.php`.
Create commit title and message.

See solution in the `lilliputten-11ty-site/src/articles/2025/gulp-lqip-plugin/gulp-lqip-plugin.md`.

`index.php` should receive an optional `list` parameter (`index.php?list=XXX`): then to filter only items under the `$list` folder. Add a redirect: `/list/XXX` -> `index.php?list=XXX`. Add links (based on the `useRedirectMode`) to the folders list for the folder sections (`section-title` nodes). Add `up` icons in the `view.php` (alongside "home" ones), to return to the upper-level 'list' page (also based on the `useRedirectMode`).

---

Use FQDN links for all local links like `styles.css`, `view.css, `index.css` and `styles.js` and `favicon.ico`: It my be refered as `list/favicon.ico` if there is 'list' mode.

---

Don't store LQIP in the cache -- as they're already stored as files by `generateThumbnail`.
