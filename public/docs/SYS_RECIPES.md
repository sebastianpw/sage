# The `dumpcode.sh` Recipe System
This document explains the underlying versioning and storage system for the `dumpcode.sh` recipes. The system is designed to be both a perfect historical record and highly efficient with storage, preventing data duplication.
The core principle is **content-addressed storage**: the content of each file or database schema is stored only once, identified by its unique SHA-256 hash (its "digital fingerprint").
---
## Scenario 1: A New or Modified Recipe
When you run `dumpcode.sh` with a new set of files, or with files that have been modified since the last run, the process is straightforward:
1.  A new `recipe` record is created.
2.  For each new or changed ingredient, the script calculates its unique hash.
3.  Since this hash doesn't exist in the `recipe_ingredient_snapshots` table, the script inserts the **full content** of the ingredient into a new snapshot record.
4.  The new recipe is linked to these brand new snapshots.
This ensures that every unique version of every file is captured and stored.
---
## Scenario 2: Rerunning an Unchanged Recipe
This is where the intelligence of the system shines. Let's explore what happens if you run the exact same command again when all the files and database schemas are unchanged.
The system will create a new recipe record in your history, but it will intelligently reuse all the existing content data. There is almost no wasteful duplication.
Here is the step-by-step process:
#### 1. A New Recipe Record is Created
The script immediately creates a **new row** in the `recipes` table. This is intentional and acts as a logbook entry. This new row gets a unique `id` and a fresh `created_at` timestamp, providing a perfect audit trail of when the recipe was generated.
#### 2. Hashes are Calculated
For each ingredient (`public/bootstrap.php`, `db:videos`, etc.), the script calculates its SHA-256 hash. Because no files have changed, every single one of these hashes will be **identical** to the hashes generated during the previous run.
#### 3. Snapshots are Reused (The Magic)
This is the key to efficiency. For each ingredient, the script queries the database:
SELECT id FROM recipe_ingredient_snapshots WHERE content_hash = 'the_identical_hash';
For every single ingredient, it will **find a match**.
> Because a match is found for every hash, **no new content is stored** in the `recipe_ingredient_snapshots` table. The script simply retrieves the `id` of the existing snapshot and moves on.
#### 4. New Links are Forged
The script then creates new rows in the `recipe_ingredients` join table. Each new row links the **new** `recipe_id` (from Step 1) to the **existing** `snapshot_id` (from Step 3).
### What You'll See
This process has a clear and predictable outcome:
*   **In the UI (`view_recipes.php`):** You will see a **new entry appear at the top of the list**. It will have the same Recipe Name, File, and Ingredients count as the one you just ran, but with a newer "Created" timestamp.
*   **In the Database:**
    *   `recipes`: **+1 row** (Your new historical record)
    *   `recipe_ingredients`: **+N rows** (New links for the new recipe)
    *   `recipe_ingredient_snapshots`: **+0 rows** (Maximum storage efficiency!)
    *   `recipe_groups`: **+0 rows**
### Why This Behavior is Ideal
This approach provides the best of both worlds:
*   **Perfect History:** You get a complete and accurate log of every time you generated a recipe bundle, which is invaluable for tracking your work.
*   **Extreme Storage Efficiency:** You are not bloating your database with redundant copies of the same files. The content is stored once, and every identical recipe just points to it. This is the same principle that makes version control systems like Git so fast and efficient.
