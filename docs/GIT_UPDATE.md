# Git — fetch, update, and push (NPPC Lab LMS)

Remote: `origin` → `https://github.com/Rhemjohn-Dave/nppc-lab.git` · main branch: `main`.

Commands work in PowerShell (Windows dev PC) and bash (Ubuntu server).

## 1. Check where you are

```bash
git status            # changed / untracked files
git branch --show-current
git remote -v         # confirm origin URL
```

## 2. Fetch (see what changed on GitHub — does not touch your files)

```bash
git fetch origin
git log --oneline HEAD..origin/main   # commits on GitHub you don't have yet
git log --oneline origin/main..HEAD   # your local commits not pushed yet
git diff --stat HEAD origin/main      # files that differ
```

`fetch` only downloads; your working files stay the same until you pull/merge.

## 3. Update your local copy (pull)

Clean working tree (`git status` shows nothing to commit):

```bash
git pull origin main
```

You have uncommitted changes you want to keep:

```bash
git stash push -u -m "wip before pull"
git pull origin main
git stash pop          # re-apply your changes; resolve conflicts if any
```

Prefer a linear history (no merge commit) when you also have local commits:

```bash
git pull --rebase origin main
```

## 4. After pulling — refresh the app

Local (Windows dev):

```bash
composer install
php artisan migrate
npm install
npm run build          # or: composer dev
php artisan optimize:clear
```

Production server: follow **Subsequent deploys** in [`DEPLOY.md`](DEPLOY.md) (`git pull`, `composer install --no-dev`, `migrate --force`, `npm ci && npm run build`, cache commands, `queue:restart`). Never run `migrate:fresh`, `db:wipe`, or `--seed` on production.

## 5. Save and push your own changes

```bash
git status
git add -A                       # or add specific paths
git commit -m "Short description of the change"
git fetch origin
git pull --rebase origin main    # get others' work first
php artisan test                 # optional but recommended
git push origin main
```

Do not commit `.env`, `storage/` runtime files, `vendor/`, `node_modules/`, or `public/build/` (they are ignored or generated). Generated controlled-form PDFs under `storage/app/private/controlled_forms/` are runtime data — don't commit them unless intentionally shipping a default form.

## 6. Resolving conflicts

When `pull`, `rebase`, or `stash pop` reports a conflict:

```bash
git status                       # lists "both modified" files
# open each file, keep the right code between <<<<<<< ======= >>>>>>> markers
git add path/to/file
git rebase --continue            # if rebasing
# or: git commit                 # if merging
```

Give up and return to the state before the pull:

```bash
git rebase --abort               # during a rebase
git merge --abort                # during a merge
```

## 7. Useful extras

```bash
git log --oneline -n 20          # recent history
git diff                         # unstaged changes
git restore path/to/file         # discard local edits to one file (irreversible)
git switch -c feature/my-change  # work on a branch instead of main
git push -u origin feature/my-change
```

Throw away **all** local changes and match GitHub exactly (destructive — commit or stash first if unsure):

```bash
git fetch origin
git reset --hard origin/main
git clean -fd                    # also deletes untracked files
```
