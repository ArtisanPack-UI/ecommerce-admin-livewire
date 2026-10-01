---
title: Product CSV import and export
---

# Product CSV import and export

The products screen exports the catalog as CSV, and **Products → Import** reads the same format back. A file exported,
edited in a spreadsheet, and imported again updates the products it came from.

## Export

On the products screen:

- **Export catalog** exports every product matching the current search and filters.
- With rows selected, the **Export catalog** bulk action exports just those products.

A product with variants is written as one product row followed by one row per variant. Exports stop at
`tables.export_max_rows` rows.

## Import

1. **Upload** a CSV file (`.csv` or `.txt`, up to 10 MB and `imports.max_rows` rows). It is copied to the
   `imports.disk` disk.
2. **Match columns.** Each header in the file is matched to a column below. Headers that match a column name (ignoring
   case, spaces, and punctuation) are matched for you; anything else can be ignored.
3. **Check.** Every row is checked without writing anything, and the report lists the products that will be created,
   updated, and the rows with errors (with their line number and the reason, such as "Another product or variant already
   uses the SKU").
4. **Import.** The rows are applied by a queued job (on `imports.queue`, or the default queue). The page shows the
   progress. Each row is saved on its own, so a row with an error is skipped and the rest still import.

- An import that stops part-way can be **resumed**; it carries on from the next row. A row whose write finished just
  before the worker stopped may be applied again; with a SKU or slug that is a harmless update.
- Only one worker runs an import at a time. The job may run for up to an hour, so set the queue connection's
  `retry_after` above 3600 seconds, or the queue will hand the job out again while it is still running.
- A leading apostrophe that the export added in front of `=`, `+`, `-`, or `@` is removed again on import.
- Completed imports are pruned after seven days.
- The failed rows can be downloaded as CSV.
- The uploaded file is deleted when the import completes or is discarded.
- The screen needs `product.create`. Each row also needs `product.create` (new products) or `product.update` (existing
  ones) for the user who started the import.

### How rows are matched

- A **product row** updates the product with the same `sku`; failing that, the same `slug`. Otherwise it creates a
  product.
- A **variant row** (one with `variant_sku` or `variant_name`) updates the variant with the same `variant_sku`.
  Otherwise it creates a variant under the product its `sku` or `slug` names, which may be created by an earlier row of
  the same file.

On an existing product or variant, **an empty cell leaves that value alone.** On a new product, empty cells use the
defaults (`simple` type, `draft` status).

## Columns

| Column | Product row | Variant row | Format |
|---|---|---|---|
| `type` | Product type key, e.g. `simple`, `variable`, `digital`. Defaults to `simple`. | Ignored | Registered type key |
| `name` | Required for new products | Ignored | Text |
| `slug` | Matches, or sets, the slug | Finds the product | Text (made URL-safe) |
| `sku` | Matches, or sets, the SKU | Finds the product | Text |
| `status` | `draft`, `active`, or `archived` | Ignored | |
| `short_description`, `description` | HTML; unsafe markup is removed | Ignored | Text or HTML |
| `barcode` | | Ignored | Text |
| `categories` | Replaces the product's categories | Ignored | Category slugs separated by `\|` |
| `tags` | Replaces the product's tags; new tags are created | Ignored | Tag names separated by `\|` |
| `weight`, `length`, `width`, `height` | | Ignored | Decimal number |
| `weight_unit`, `dim_unit` | | Ignored | e.g. `kg`, `cm` |
| `is_taxable` | | Ignored | Yes/no |
| `tax_class_key` | | Ignored | An existing tax class key |
| `track_inventory`, `allow_backorder` | Stock settings | Stock settings | Yes/no |
| `quantity_on_hand` | Sets the count; changes are logged as "CSV import" | Same | Whole number |
| `low_stock_threshold` | | | Whole number |
| `variant_sku` | — | Matches, or sets, the variant SKU (required) | Text |
| `variant_name` | — | Variant name | Text |
| `price_{CUR}` | Price in that currency, e.g. `price_USD` | Same | Amount, e.g. `12.50` |
| `compare_at_price_{CUR}` | Compare-at price; needs `price_{CUR}` in the same row | Same | Amount |

- Yes/no cells accept `1`/`0`, `yes`/`no`, `y`/`n`, and `true`/`false`.
- There is one `price_{CUR}` / `compare_at_price_{CUR}` pair for each currency the store has enabled. An import sets
  the product's regular price in that currency; scheduled prices are left alone.
- Cells that start with `=`, `+`, `-`, or `@` are exported with a leading apostrophe so spreadsheets do not run them
  as formulas.

## Sample file

**Download a sample file** on the import screen, or copy
[`resources/samples/products-import-sample.csv`](../resources/samples/products-import-sample.csv). It creates a simple
product, a variable product with two variants, and a digital product.
