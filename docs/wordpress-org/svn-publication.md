# First WordPress.org SVN publication

Approval creates an SVN repository but does not publish the plugin automatically.

## Prepare the working copy

1. Generate a WordPress.org SVN password from the WordPress.org profile settings.
2. Install Apache Subversion or TortoiseSVN and confirm `svn --version` works.
3. Download and verify the public `image-usage-audit.zip` release package.
4. Run `scripts/prepare-wordpress-org-svn.ps1` with the approved slug, version, ZIP path, empty working directory, and `.wordpress-org` as the asset source.
5. Review `svn status` and `svn diff` before committing.

## Expected layout

```text
image-usage-audit/
├── assets/
│   ├── banner-772x250.png
│   ├── banner-1544x500.png
│   ├── icon-128x128.png
│   ├── icon-256x256.png
│   └── screenshot-N.png
├── tags/
│   └── 2.2.9/
└── trunk/
```

The WordPress.org directory graphics belong in the SVN root `/assets`. Runtime CSS and JavaScript remain inside the plugin package under `/trunk/assets` and `/tags/2.2.9/assets`.

## Final checks

- plugin files are directly inside `/trunk`, not inside a second nested plugin directory;
- plugin header version and `Stable tag` are both `2.2.9`;
- `/tags/2.2.9` matches `/trunk` for the first publication;
- no ZIP archives, test fixtures, repository metadata, or development dependencies are committed;
- screenshots contain no private information;
- every screenshot has a matching readme caption;
- image MIME properties are set;
- one clean publication commit is used.

Commit only after review:

```powershell
svn commit -m "Publish Image Usage Audit 2.2.9" --username ussmarines
```

After publication, install the plugin from WordPress.org on a clean test site and verify the displayed version, activation, scan flow, directory graphics, and screenshots.
