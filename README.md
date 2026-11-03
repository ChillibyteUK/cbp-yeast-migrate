# Yoast to Yeast Migration

Copies per-post SEO data from Yoast SEO's post meta into
[CBP Yeast SEO](../cbp-yeast-seo). Yoast can be deactivated first: values are
read straight from the database, and Yoast's data is never changed or deleted.

## What is migrated

| Yoast | Yeast |
| --- | --- |
| SEO title | Page title |
| Meta description | Meta description |
| Social title / description | Open Graph title / description |
| Facebook image (URL, else attachment ID) | Open Graph image |
| Twitter image (URL, else attachment ID) | Twitter/X image |
| Canonical URL | Canonical URL |
| Meta robots noindex (1 = noindex, 2 = index, 0 = post type default) | Indexing |

- `%%variables%%` (title, sitename, sep, category, excerpt, date, `%%cf_*%%`
  and so on) are expanded to literal text, because Yeast stores plain strings.
  Values with a variable that can't be resolved are skipped and reported.
- Existing Yeast values are kept unless **Overwrite** is ticked; empty Yoast
  values never blank a Yeast value. Re-running is safe.

## Not migrated

- Nofollow (Yeast has none; reported per post), focus keyphrase, cornerstone
  flags, breadcrumb titles.
- Twitter-specific title/description (Yeast reuses the Open Graph ones).
- Posts with no custom title: Yoast's post type title templates are not baked in.
- Term, author and homepage/archive SEO, and Yoast's global settings.

## Use

**Tools → Yoast to Yeast** (dry run is ticked by default), or:

```bash
wp cbp-yeast-migrate run --dry-run
wp cbp-yeast-migrate run [--overwrite]
```

Requires the canonical field added in Yeast SEO (`cbp_yeast_seo_canonical`).
# cbp-yeast-migrate
