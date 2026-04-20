- Return to the "current" folder's anchor link.
- `thumb.php` to check/create thumb cache and return redirect to it.
- `view.php` to allow slighly overscale, over the original image width.
- Use LQIP thumbnails in pages. See `D:\Work\Me\11ty-site\lilliputten-11ty-site\src\articles\2025\gulp-lqip-plugin\gulp-lqip-plugin.md`.

`index.php` should receive an optional `list` parameter (`index.php?list=XXX`): then to filter only items under the `$list` folder. Add a redirect: `/list/XXX` -> `index.php?list=XXX`. Add links (based on the `useRedirectMode`) to the folders list for the folder sections (`section-title` nodes). Add `up` icons in the `view.php` (alongside "home" ones), to return to the upper-level 'list' page (also based on the `useRedirectMode`).
