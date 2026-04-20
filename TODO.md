`thumb.php` should to check/create thumb cache and return a redirect to it.

- `view.php` should allow slighly overscale for the images, over the original image width.

- Use LQIP thumbnails in pages.

See solution in the `lilliputten-11ty-site/src/articles/2025/gulp-lqip-plugin/gulp-lqip-plugin.md`.

`index.php` should receive an optional `list` parameter (`index.php?list=XXX`): then to filter only items under the `$list` folder. Add a redirect: `/list/XXX` -> `index.php?list=XXX`. Add links (based on the `useRedirectMode`) to the folders list for the folder sections (`section-title` nodes). Add `up` icons in the `view.php` (alongside "home" ones), to return to the upper-level 'list' page (also based on the `useRedirectMode`).
