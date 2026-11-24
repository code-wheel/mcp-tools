# Changelog

All notable changes to the MCP Tools module will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [1.0.0-beta27] - 2026-11-23

### Security

- **The tools follow Drupal's field access, not only its entity access.**
  Where tools follow entity access (new sites), a content or batch tool
  that creates, updates or publishes a node checks each field it sets.
  On a node, the owner, the published status, the dates, promote and
  sticky are for "administer nodes", as on Drupal's node form. Before, a
  writer could create content owned by user 1, give their node to
  someone else, or publish without that permission. Sites that updated
  from before 1.0.0-beta23 keep the old behavior until they turn
  `access.entity_access` on.

### Changed

- **One field type table for both field tools.** `mcp_paragraphs_add_field`
  had its own copy: it did not take `timestamp`, took a type whose module
  is off (Drupal then failed), and its text named 6 types and "etc.".
  It now takes the same types as `mcp_structure_add_field`, refuses one
  whose module is off with the ones the site has, and names them all.

- **Each submodule owns its permission.** The base module defined the
  permission of every category, so the permissions page listed all 36,
  also for submodules that are off, and uninstalling a submodule left its
  permission on the roles. Now each submodule defines its own
  (`mcp_tools use metatag` in MCP Tools - Metatag, and so on), and the
  base module the six its own tools use. `drush updb` takes from roles the
  permissions of submodules that are off and saves each role, so an
  uninstall revokes the submodule's permission from then on.

- **One group on the Extend page.** The base module and its submodules
  were under "AI", "MCP" and "MCP Tools"; all are under "MCP Tools" now,
  each named "MCP Tools - X". Machine names are as they were.

- **The export is the mirror of the import.** `mcp_migration_export_json`
  and `mcp_migration_export_csv` take `entity_type` (node, taxonomy_term,
  redirect) and give the rows the import of that type reads back. With
  `references: "names"`, tags and a term's parent go by name, so an export
  from one site imports into another, a term tree included.

### Removed

- Six category permissions that let nothing through: `mcp_tools use
  site_tools`, `content_tools`, `config_tools`, `user_tools`,
  `structure_tools` (older names of five categories, which no code
  checked) and `workflows` (no tool used it). `drush updb` takes them from
  the roles. The remote setup no longer grants them.

### Deprecated

- **`mcp_structure_list_content_types`**, to be removed in a later
  release. `mcp_tools_list_content_types` gives the same, with each type's
  fields, and now its `content_count` too, counted with access checked
  (the structure tool counted all content, also what the user may not
  see). One tool for one question.

- **`mcp_migration_validate`**, to be removed in a later release. Use
  `mcp_migration_import_json` (or `_csv`) with `dry_run`: it runs the
  checks the import itself runs (the key, references by id or name, a
  term's parent), so it cannot drift from the import, and writes nothing.
  The tool says so in its description and in its answer.

### Added

- **A field that holds paragraphs.** The `paragraphs` field type in
  `mcp_structure_add_field`, `mcp_structure_scaffold_content_type` and
  `mcp_paragraphs_add_field`: paragraphs on a content type, or inside a
  paragraph type. No tool could add one before, so a model could make
  paragraph types but not use them. It is an entity reference revisions
  field with the Paragraphs widget, holds many by default, and
  `target_bundles` names the paragraph types. Offered when Paragraphs is
  on.

- **MCP Tools - Undo** (`mcp_tools_undo`, new): undo what one tool call
  changed. While it is on, each call's changes are kept:
  content made, changed or deleted, and config. `mcp_undo_call` puts them
  back, newest first: what the call made is deleted, what it changed gets
  the revision before as a new revision (or its values), what it deleted
  is made again, and config gets its data back. When anything was changed
  again since, the undo is refused and nothing is undone. It asks first.
  `mcp_undo_recent` lists the recent calls.
  - Each call is an **MCP tool call** record (an entity), kept 30 days by
    default (`keep_days`) and removed on cron.
  - **Reports > MCP tool calls** shows people what the AI changed, with
    an Undo button on each call. Permissions: "See what MCP tool calls
    changed" and "Undo MCP tool calls". The records have Views data.

### Fixed

- **A redirect's new destination broke it.** `mcp_redirect_update` stored
  `internal:/internal:/node/2` and answered success: the redirect led
  nowhere after the first change of its destination. It now stores it as
  the create and import tools do.
- **A menu link's parent could only be guessed.** A wrong parent is
  refused with "give the plugin_id or the id", and
  `mcp_tools_get_menu_tree` showed neither. Each link in the tree now has
  `plugin_id` and `id`.
- **An edit of a waiting draft says it is the draft.** The answer of
  `mcp_update_content` gave the draft's title only, so a model that had
  found the live title "corrected" it back. It now says `draft`, gives
  `live_title`, and says the draft's changes are its own.
- **The import's key follows the column mapping.** With a `field_mapping`
  that renames the key column, the key given as the CSV has it failed
  every row, and the call answered success with nothing made. Both names
  work now, and a key no row has is refused with the columns there are.
- **The term tools take a parent by name.** `mcp_structure_create_terms`
  and `mcp_batch_create_terms` took an id only: given a name, the first
  answered "Tool execution failed." and nothing else, the second stored a
  parent that was no term. A parent may now be a term's id or name, also
  of a term made earlier in the same call, and a term that is not made
  is named in the error with why.

- **An import run again makes again what was deleted by hand.** Migrate's
  record still said the row was imported, so a rerun skipped it and
  answered success with nothing made.
- **New content on a moderated type says why a status is refused.**
  `mcp_create_content` with `status` on a type under a moderation workflow
  answers `MODERATED` and points to `mcp_moderation_set_state`, as an
  update does. The workflow's state decides the status.
- **The CSV export keeps every value of a reference field,** joined by
  `|`. It kept the first one only.
- **A yes/no or a whole number is taken only when its meaning is plain.**
  Before the input check, a boolean input turned anything it did not
  know ("garbage", `2`, `"1.0"`) into FALSE, and an integer input cut
  `"1.9"` to 1 and read `"1e3"` as 1000. The call then went on with a
  value nobody sent. Now only plain values are cast (`true`, `"1"`,
  `"no"`, `"5"`, `5.0`), and anything else is refused by the input
  check as it was sent.

## [1.0.0-beta25] - 2026-11-23

### Changed

- **The import runs on Drupal's Migrate API, and needs a key.**
  `mcp_migration_import_json` and `mcp_migration_import_csv` take `key`,
  required: a column whose value names a row for good, such as an id
  from the source, or `uuid`. An import with the same content type and
  key is one migration (migrate_plus), so a second run updates what the
  first made instead of making copies, and `drush migrate:import
  <import_id>` (migrate_tools) runs it too. mcp_tools_migration now
  requires migrate_plus.
  **Updating a site that has MCP Tools - Migration on:** Composer does
  not bring migrate_plus. Run `composer require drupal/migrate_plus`
  before `drush updb`, and the update turns on migrate and migrate_plus.
  Without it the site works, the exports and field tools work, and the
  import says what to run.
  - `dry_run` says for each row whether it would be created or updated,
    and writes nothing.
  - 100 rows run a call; the answer has `remaining` and `done`. Up to
    1000 rows an import.
  - Every node is checked by Drupal's validation before it is saved.
  - Reference fields take ids or names, several with `|`. A name that
    matches nothing refuses its row.
  - `mcp_migration_import_status` reads the import's own record (made,
    failed, why), for one `import_id` or for all.
  - `mcp_migration_rollback` (new, asks first) deletes what one import
    made. Nodes it only updated are kept.
  - The exports take `offset`, go in id order, and give `uuid`,
    `total` and `next_offset`, so an export imports back as updates.
- **Terms and redirects go through the same import** (`entity_type`
  `taxonomy_term` or `redirect`). Terms are keyed by name and redirects
  by source when no key is given. A term's `parent` may name another row,
  in any order, so a whole tree goes in one call. A term or redirect that
  was there and not made by the import is left as it is and listed under
  `existing`. A redirect to nowhere is made and said under `warnings`.
  `mcp_batch_create_terms` and `mcp_redirect_import` stay for sites
  without migrate_plus.

### Fixed

These all answered success:

- A CSV value with a quoted comma lost its row.
- A row with too few or too many columns was dropped without a word.
- A column the content type does not have was dropped. It is refused
  now, with the fields there are.
- The same import twice made everything twice.
- A tag given by name was stored as 0, and `1|2` as 1 only.
- The export stopped at 100 and could not go further.
- `mcp_redirect_import` ran any number of redirects in one request. Use
  the import for long lists; it runs 100 a call.
- **A tool does not change a layout someone is editing.** Layout Builder
  keeps an editor's unsaved changes, and their save writes the whole
  layout back, so a section or block a tool added in between was lost.
  The layout tools now refuse with `LAYOUT_BEING_EDITED` until the editor
  saves or discards.
- **A name where an id goes is said as such everywhere.** JSON:API writes,
  and every save Drupal's validation checks in a tool call, answer "give
  its id, not its name". On PostgreSQL they answered with a database
  error.

### Removed

- `BatchService::createMultipleRedirects`, which no tool called. Many
  redirects at once are `mcp_redirect_import`, which also checks that
  each destination leads somewhere.

## [1.0.0-beta24] - 2026-11-23

### Security

- **Clone with references takes real field names, counts every clone,
  and is all or nothing.** A field name that is not a reference field was
  skipped, so the clone shared the original's references while the answer
  said success. It is refused now with the fields there are. Each clone
  counts against the write limits, and a failure halfway undoes the
  clones made so far.
- **A template counts every part it makes against the limits.**
  `mcp_templates_apply` made dozens of content types, fields, roles and
  views as one write. Each part counts now. A queue run still counts as
  one: its items are the site's own queued jobs.
- **Translation writes follow Drupal's translation access.** Where tools
  follow entity access, creating or updating a translation also needs
  Drupal's "create content translations" or "update content
  translations", as its translation forms do. The tools asked only for a
  translate permission.
- **Two calls at once cannot both get under the write limit.** The
  limiter read and wrote its counters in two steps. The check and the
  count run under one lock per client now; a call that cannot get it is
  refused as busy, not let through uncounted.

### Fixed
- **Every entity a tool saves passes Drupal's validation.** The tools
  saved without it, so a user's email "nope", an image effect with no
  width, or a title longer than its field was stored, and the call said
  success. Now, during a tool call, each entity is checked before it is
  saved: its fields, its references, its config schema. Only what the
  save adds is refused, so a value that was wrong before does not stop
  an unrelated change, and saves outside a tool call are not touched.
  The answer names the field and what is wrong.
- **The sitemap is generated when the tool says so.**
  `mcp_sitemap_regenerate` started Simple Sitemap's form batch, which
  nothing ran: it said "regeneration started" and no sitemap changed. It
  now generates in Simple Sitemap's backend mode, for a few seconds a
  call, and answers with `remaining` and `done`. `continue: true` goes
  on where the last call stopped. A generation that cannot get its lock
  is refused, not skipped.
- **Alias generation reaches every entity.** `mcp_pathauto_generate`
  took the first 500 entities in no order on every call, so on a bigger
  site the rest never got an alias. It now goes in id order, 50 a call,
  and answers with `next_after`, `remaining` and `done`: give `after`
  to the next call. Each alias counts against the write limits.
- **Search indexing does a bounded part and says when nothing
  happened.** `mcp_search_api_index` took any limit (100000 ran the
  whole index in one request), and answered "Indexed 0 items" with
  success when another run held the index or the backend failed. A call
  does at most 200 now, a server that is not available is refused, and
  a call that indexed nothing while items wait is a failure that says
  where to look.
- **A queue is run as Drupal's cron runs it.** `mcp_cron_run_queue`
  counted a worker's "requeue" or "delay" as a failure, went on after a
  worker suspended the queue, left an item claimed for an hour after a
  PHP error, ran as the tool user, and answered success with
  "Processed 0" when every item failed. It now handles a queue the way
  core's cron does, as the anonymous user, for at most 200 items and 20
  seconds a call, and a run in which every item failed is a failure with
  the workers' errors.
- **The cron interval goes where Drupal reads it.**
  `mcp_cron_update_settings` wrote `system.cron:threshold.autorun`, a
  Drupal 7 key that nothing reads (and that has no schema), and said
  "updated". `mcp_cron_get_status` read it. Both use Automated Cron's
  interval now. Without Automated Cron the update is refused, and says
  that cron then runs only from outside.
- **The cron status says the real interval under Drush.** Drush sets
  Automated Cron's interval to 0 in its own requests, so over the STDIO
  server `mcp_cron_status` said cron never runs by itself. It reads the
  stored interval, the one web requests run cron by.
- **A hidden block label is hidden.** `label_display: "hidden"` was
  stored as is, and Drupal shows any value that is not empty. Hidden is
  `'0'` now; another value is refused.
- **A menu link's parent must be there, in the same menu.** Drupal put
  a link with an unknown parent at the top, and one with a parent in
  another menu into that menu, while the answer named the menu asked
  for. Both are refused, and a parent can be given by its link id.
- **Clearing an entity's cache clears its own tags,** and is refused for
  an entity that does not exist (it said "cleared"). A config entity
  gets its real tag, such as `config:user.role.editor`.
- **Turning custom layouts off asks first.** `mcp_layout_allow_custom`
  with `allow: false` deletes every custom layout of the bundle for good
  (Drupal drops their field), and did not ask. When there are custom
  layouts, it now asks like a destructive tool, and says how many.
- **Webform status and settings are webform's own.** Any status but
  exactly "open" closed the form, "Open" too. Now open, closed or
  scheduled, and anything else is refused. A setting webform does not
  have was stored and did nothing; it is refused with the settings meant
  most likely.
- **"No security issues" is not said when Drupal could not look.**
  `mcp_tools_check_security_updates` left out the projects Drupal could
  not check, so a site whose update data could not be fetched read as
  having no security issues, and a fetch that failed outright was a
  success with the error inside. The answer now has `complete`,
  `not_checked` and `last_checked`, and a fetch that failed is a
  failure.
- **Unused fields on vocabularies are found.**
  `mcp_analysis_find_unused_fields` filtered terms on `type` (their
  bundle key is `vid`): the query failed, the field was skipped, and the
  answer was "All configured fields are in use". Each entity type's own
  bundle key is used now, and a field that could not be checked is
  listed, not skipped.
- **The SEO check reads Metatag 2.** It read the meta tags with
  `unserialize()`, and Metatag 2 stores JSON, so every node was
  "missing a meta description". It asks Metatag's own manager now,
  defaults included.
- **An Ultimate Cron job that failed is not "executed successfully".**
  Ultimate Cron catches what a job throws, logs it and answers FALSE,
  as it does when it cannot get the job's lock. `mcp_ultimate_cron_run`
  ignored the answer. It fails now, with the job's last log message.
- **Theme settings go to an installed theme, and fit its schema.**
  `mcp_theme_update_settings` saved settings for a theme that was not
  installed, which made its config ahead of time and then stopped the
  theme from being installed. And it stored any key and any value. It
  refuses an uninstalled theme now, and settings the theme's config
  schema does not take.
- **A draft waiting over published content is what the tools work on.**
  The moderation, content, metatag and media alt-text tools loaded the
  published revision. With a newer draft waiting, `published` answered
  "already published" and left the draft, and an edit was saved on top
  of the published text, burying the draft. They load the newest
  revision now, as Drupal's "Latest version" tab does. On moderated
  content, `mcp_publish_content` and a `status` given to
  `mcp_update_content` are refused with the moderation tool to use:
  Content Moderation reset the status, and the tool said "published".
- **A recipe's inputs are used.** `mcp_recipes_apply` never collected
  them, so Drupal had none and every `${name}` placeholder was stored as
  written. It takes `inputs` now (by name; one not given gets its
  default), and refuses a name the recipe has no input for.
- **A recipe that installs a theme is valid.** `mcp_recipes_validate`
  checked every `install` entry as a module, so core's own
  `core_recommended_front_end_theme` failed ("Required module olivero is
  not available") and `mcp_recipes_apply` refused it. Themes count now,
  and validation also runs Drupal's own checks
  (`Recipe::createFromDirectory()`), the ones applying would run.
- **Config export writes what `drush config:export` writes.**
  `mcp_config_export` copied the raw active storage: the site's
  `config_exclude_modules` (and config_split, config_ignore) were not
  applied, and translations were never written. It exports through
  Drupal's export storage now, every collection.
- **A view Views refuses is not saved.** `mcp_create_view` and
  `mcp_add_view_display` saved what Views itself calls broken: a page
  display with no path, a feed with a style it cannot use. Views' own
  check runs before the save now, and its words are the answer.
- **A field block in a layout knows which entity it shows.**
  `mcp_layout_add_block` saved only the block's id, so a field block had
  no context mapping and a page of that bundle could not render it. It
  saves the block as Layout Builder's own form does now. A section added
  past the end answers with the position it really got.
- **A media type is made the way Drupal's form makes it.** The template
  tool made a media type with no source field, so nobody could add media
  of it, and the media tool skipped the form and view displays, so the
  add form had no file widget. Both use one builder now: the type, its
  source field, and the displays the source prepares, undone again when
  a step fails.
- **A template role gets its permissions in one save.** It was saved
  first, and a permission the site lacks then failed the second save:
  an empty role, which a second apply skipped. The role is made with the
  permissions the site has, and the answer names those it has not.
- **A paragraph field a translation does not have is refused.**
  `mcp_update_content` with `language` and `paragraphs` skipped a field
  the paragraph does not have, counted it as updated and said success.
  Such fields are refused now, before anything is saved.
- **A term's parent must be a term of the same vocabulary.**
  `mcp_structure_create_term` stored any parent id: a term whose parent
  is not there dropped out of the vocabulary's tree and overview.
- **Sitemap entity settings keep what was not given, in every variant.**
  `mcp_sitemap_set_entity` filled in defaults for what it was not given
  (setting `changefreq` alone turned indexing back on and reset the
  priority), wrote only the first sitemap variant, and "updated" a bundle
  that does not exist or a type Simple Sitemap does not index. Now only
  the given settings change, in every variant, and the others are
  refused.
- **An image effect is added with a weight.** `mcp_image_styles_add_effect`
  gave the effect none, which Drupal's validation refuses, so with every
  save checked nothing was saved. It gets the next weight after the
  effects there are, as Drupal's form does, and its id is no longer stored
  in its settings.
- **Content types and vocabularies without a description are saved
  with none,** not with an empty text, which Drupal's schema refuses.
- **Text is saved in a format the user may use.** The tools picked
  `basic_html` whenever the site had it, also for a user who may not
  use it, and Drupal refuses that. Such a user gets the fallback format.

## [1.0.0-beta23] - 2026-11-22

### Security

- **The remote image fetch checks every address it is sent to, and
  cleans an SVG whatever the server calls it.** `mcp_remote_fetch_image`
  checked that the first address was not internal, then followed up to
  three redirects without a check: a public server could send the fetch
  on to 127.0.0.1 or a cloud metadata address, and what came back was
  saved as a file. Each redirect is checked now, and the first request
  goes to the address that was checked. An SVG was cleaned only when the
  server sent it as `image/svg+xml`: sent as `image/png`, it was saved
  with its scripts. The file's content now decides how it is cleaned
  and what extension it gets.
- **A tool may do to an entity what its user may do in Drupal.** The
  content, media, scheduler, metatag, clone, moderation, user and batch
  tools checked the connection's scope and the tool's permission, not
  the entity: an editor who may edit only their own articles edited
  anyone's through a tool. Now Drupal's own access check decides, as on
  the site's forms. **A new site has it on. A site that updates keeps
  what it had** until someone ticks "Tools follow Drupal entity access"
  on the settings form: give the tool user the Drupal permissions it
  needs first. `drush mcp-tools:remote-setup` now grants the content and
  media permissions for the categories it sets up.
- **No secrets where a read session or a log can see them.**
  `mcp_config_get_diff` returned secrets raw (get_config redacted them),
  `mcp_cron_get_status` returned the cron key, the tool log put full
  arguments and results in the logger context (syslog and monolog keep
  them), and the watchdog tool returned backtraces with absolute paths.
- **New views are not open to everyone.** `mcp_create_view` saved a view
  with no access rule: a page of users was a public list of user names.
  A template's views listed unpublished titles at `/articles`.
- **Read tools show only what the user may see.** Search results,
  the content audit, the duplicate finder and the link scan gave out
  titles of unpublished content.
- **The JSON:API tools hold to their allowlist and to field access,**
  and validate what they save.
- **A new User-Agent is not a new rate limit bucket.**
- **A batch counts each item against the write limits.** With three
  writes a minute allowed, one batch made fifty nodes: a call counted as
  one write. The batch tools, the content import and the redirect import
  count their items now, and batch delete counts against the delete
  limit. A call bigger than what is left is refused with how many items
  fit.
- **Our tools hold the "ask first" rule on every way in.** On a site
  that insists on a yes, a strong call (a tool that declares itself
  destructive, a restricted permission, an unreviewed recipe) was
  refused by our server, our Drupal AI bridge, and MCP Server with
  `mcp_tools_policy`. Through MCP Server's tool bridge without that
  submodule, Tool API's own AI connector, or another module's agent,
  our tools ran it. Tool API tells a tool who called it (the invoker).
  Our tools now refuse such a call themselves when the caller is not a
  person and nobody said yes. A person stays free: Drush, a form such
  as Tool Explorer's, and the site's own code. The rule covers our 227
  tools. Other modules' tools are theirs: that would need a place in
  Tool API where any rule can refuse a call.

### Measured

On a site of 24 content types and 33 items, twelve harder tasks, the
local model `gpt-oss:20b`, ten runs each: 1.0.0-beta22 did 100 of 120
with 631 tool calls, this release 118 of 120 with 501.

### Fixed

- **Content can be counted.** `mcp_tools_get_recent_content` takes
  `status` ("published" or "unpublished") and answers with `matching`,
  how many items match in all, also beyond the limit. Asked how many
  articles are published, a local model took `total` (the items in the
  answer) for the count, and could not filter by status at all.
- **The content type list can be asked for one field.**
  `mcp_tools_list_content_types` takes `field` and lists only the types
  that have it. On a site with 24 types a model read the whole list four
  times and then asked for each type on its own: 14 calls for one
  question.
- **A tool name with the wrong module part runs the tool, when only one
  can be meant.** `mcp_tools_list_field_types` for
  `mcp_structure_list_field_types`, `mcp_tools_get_role_permissions`
  for `mcp_structure_get_role_permissions`. With the first word after
  `mcp_` taken off, the rest (two words at least) must end exactly one
  real name. Where two could be meant, the closest names answer as
  before. The same checks as for the full name.
- **A number or a yes/no given to a text input arrives as text.** A
  model sent `status: 1`, and the input check refused it before the
  tool could read it. `status` takes 1, 0, true and false as well.
- **A list or an object sent as its JSON text is read as one.** Two
  local models sent structured inputs as a string of JSON:
  `nemotron-3.5-lightning` its `fields` and `updates` (37 refused calls
  in four runs), `mimo-v2.6` a permission list, which then read as one
  permission called `["access content"]`.
- **Tools that said "success" and changed nothing now refuse the
  input, and say what they take.** Found with a linter of our own that
  reads every tool's inputs: eleven list inputs did not say what an item
  is. Six of them had a fault behind them.
  - `mcp_configure_block` wanted its `visibility` in another shape than
    `mcp_place_block`, and given the place tool's shape it stored the
    wrong thing. Both now take one shape, keyed by condition:
    `{"request_path": {"pages": "/about"}}`, `{"user_role": {"roles":
    ["authenticated"]}}`, or `{"entity_bundle:node": {"bundles":
    ["article"]}}` (`node_type` works too). The old shape still works.
  - The flat shape the release took for a page, `{"pages": "/about"}`,
    is read too (and `roles`, `bundles`). A page that is not a path (it
    must start with `/`, or be `<front>`) is refused; `\/user` is read
    as `/user`.
  - Both block tools took a `settings` key the block does not have.
    Now refused, with the settings it does have.
  - `mcp_create_webform` and `mcp_update_webform` read only plain
    element keys (`type`), though their descriptions and README showed
    `#type`. Such an element was stored empty. Both are read now, every
    property is kept, and an element with no type is refused.
    `mcp_update_webform` takes `set_elements` and `remove_elements`, to
    add, change or remove one element and keep the others: a local model
    that had to send every element back to add one often could not
    write that call.
  - `mcp_structure_add_field` and `mcp_paragraphs_add_field` stored a
    `target_bundles` name that does not exist, or a label. Now refused,
    with the bundles there are.
  - `mcp_paragraphs_add_field` failed on options given as
    `{"value": ..., "label": ...}`. Both field tools now read
    `allowed_values` the same way.
  - `mcp_templates_apply` applied nothing for a `components` name that
    is not one of its six kinds. Now refused, with the six.
- **A reference to something that is not there is refused.**
  `mcp_create_content`, `mcp_update_content`, `mcp_batch_create_content`
  and `mcp_batch_update_content` saved a reference field as given. A
  tag given by its name ("Technology"), or by an id that does not
  exist, was saved with "success", and the page showed no tag. Drupal's
  own reference check now runs before the save (the item exists, its
  bundle is allowed, it may be referenced), and only that check: every
  other value is taken as before. The answer names the field and the
  tool that finds the ids.
- **A redirect import says which redirects lead nowhere.**
  `mcp_redirect_import` now gives each such redirect a `warning`, as
  `mcp_redirect_create` does, and says how many in its message.
- **A menu link to a path where nothing is says so.**
  `mcp_menus_add_menu_link` and `mcp_menus_update_menu_link` stored any
  path and said success, the same as the redirect tools did before
  1.0.0-beta22. The link is still made, and the answer has a `warning`
  that nothing answers there and how to find the content that was
  meant. The redirect tools and the menu tools now ask one shared check.
- **The transports enable on Drupal 12.** Drupal 12 no longer calls a
  plain `hook_requirements()`. The STDIO and remote modules still had
  one, so enabling any module failed there with "Class
  mcp_tools_stdio_requirements does not exist". They now have the
  install-time class and the status report hook Drupal 11.3 brought,
  and the old function is kept, marked legacy, for Drupal 10 and early
  11. Found by running our STDIO E2E check on Drupal 12.0.0-beta2.
  It passes there now, and so do the HTTP check and the official JS and
  Python clients over HTTP. Drupal 12 is still not supported: Tool API
  does not allow it yet.
- **An empty search says where to go.** `mcp_tools_search_content`
  refused it with "Search query is required". It now says that the tool
  finds content by title, and that `mcp_tools_get_recent_content` lists
  and counts it.

## [1.0.0-beta22] - 2026-11-22

### Security

- **A new site refuses a strong call where nobody can be asked.** Three
  kinds of call run only after a person said yes: a tool that declares
  itself destructive, a grant of a permission for trusted roles only,
  and a recipe that no person has reviewed. A client that can ask its
  user is asked, as before. A client that cannot was given the call all
  the same, unless the site had "Strong calls need a confirmation"
  ticked. A site installed with this release has it ticked. **A site
  that updates keeps what it had, which is off.** 30 tools declare
  themselves destructive. To
  run such calls without a question, untick the setting, or:
  `drush config:set mcp_tools.settings access.confirm_destructive_tools 0 -y`
- **The rule holds where nobody can be asked, not only in our own
  server.** Through the Drupal AI bridge, and through MCP Server with
  the submodule `mcp_tools_policy`, nobody was asked and such a call
  ran whatever the site had set. The setting decides there now too:
  ticked, the call is refused. It is one rule in the policy that these
  ways share. Through MCP Server without `mcp_tools_policy` nothing of
  ours stands in front of the call, and it runs as before.
- **The lowest MCP SDK is 0.6.** `composer.json` allowed 0.2.2 to 0.5,
  which no test runs. Composer picks 0.10 on a new site, and 0.9 next
  to MCP Server.
- **`SECURITY.md`** says how to report a problem in private.
- **What counts as destructive: what loses something.** The five cache
  tools and `mcp_sitemap_regenerate` no longer declare themselves
  destructive, because what they clear is rebuilt. Nor do
  `mcp_disable_view`, `mcp_theme_disable` and
  `mcp_ultimate_cron_disable`, because what they switch off can be
  switched on again. `mcp_layout_disable` still does: turning Layout
  Builder off deletes the layouts of the items.
  `mcp_jsonapi_delete_entity` now does: it deletes, and it was the one
  deleting tool that did not say so, so it was never asked about. A
  test checks both rules for every tool. 30 tools declare it.

### Fixed

- **A translation includes the body.** `mcp_get_translatable_content`
  offered only the title and fields named `field_...`. The body field
  of core is called `body`, so it was not offered and not asked for,
  and `mcp_translate_content` left the source body in the new language.
  A field with another prefix was left out the same way. Every
  translatable text field a site builder added is offered now. If you
  made translations with these tools, check their body text.
- **A summary is translated with its text.** A field that has a summary
  comes with `summary` next to `value`, and takes
  `{"value": "...", "summary": "..."}`. A summary that is not given is
  left empty in the new language, and the answer says so. It used to be
  dropped without a word.
- **A translation is saved with a text format the site has.** A text
  given as `{"value": "...", "format": "..."}` with a format the site
  does not have was saved with it, and Drupal shows such a text as
  nothing. It gets the format of the field it comes from.
- **A content type that is not enabled for translation is not
  translated.** `mcp_translate_content` made a translation of any node.
  It refuses now and says where translation is enabled.
- **A text that does not fit its field is refused.** A string longer
  than its field allows was cut to that length without a word, so a
  long translated title lost its end. Nothing is saved now, and the
  answer names the field and both lengths.
- **`mcp_publish_content` names the language it changed.** Each language
  version of a node is published on its own. The tool changed the
  source language and said "unpublished", while the other versions
  stayed public. It takes `language` now, and the answer says which
  version changed and which other versions are still in the other
  state. The result has `language` and `languages`.
- **`mcp_tools_search_content` shows the language version that
  matched.** A match in a translated title came back with the title and
  the address of the source language. Each result has `language` now.
- **The hints in refusals name tools that exist.** 21 messages named 11
  tools that are not there ("Use mcp_list_users or mcp_find_user to
  locate users"). A model that is told a name calls it. Each message
  names the real tool now, or none.
- **The two file tools say why they have nothing on a site without the
  File module.** `mcp_tools_get_files` and
  `mcp_tools_find_orphaned_files` answered "Tool execution failed: The
  "file" entity type does not exist." They say now that the File module
  is not enabled.
- **`mcp_structure_setup_taxonomy` makes child terms.** With a parent
  it made the vocabulary and every term at the top level, and then
  stopped with a type error. A second try could not work, because
  everything was there by then.
- **Options given as `{"value": ..., "label": ...}` items are stored as
  options.** `mcp_structure_add_field` stored such a list as one option
  called "Array" and said success. An item that is no option is refused
  now, and nothing is made.
- **`allowed_values` on a field type without options is not dropped.**
  "integer" with options made a plain number field and said success. A
  `string` or `integer` field with options is made as `list_string` or
  `list_integer`, and the answer says so. Any other type is refused.
- **`mcp_structure_scaffold_content_type` keeps `target_bundles`.** It
  dropped them, so a reference meant for one vocabulary took terms of
  any.
- **The field type inputs name all 19 types.** The text of
  `mcp_structure_add_field` named 6, so a model picked `text_long` for
  plain long text and did not know `list_integer` exists.
- **A redirect to a path where nothing is says so.**
  `mcp_redirect_create` and `mcp_redirect_update` took any path as the
  destination and said success. A model that was asked to send a path
  to a content item it knew by title made the path up. The redirect is
  still made, because a page can come later. The answer now says that
  nothing answers there (no page, no path alias, no file) and how to
  find the content that was meant. It is in `message` and in a new
  output `warning`. Another site's address is not looked at.

### Changed

- **A tool name with only `tools_` put in runs the tool too.** The
  reverse: `mcp_tools_redirect_create` for `mcp_redirect_create` (3
  failed calls in 34 runs of the same model). The same checks apply.
- **A tool name with only `tools_` left out runs the tool.** 23 tools
  are named `mcp_tools_...` and 204 are not, and a model follows the
  many: it calls `mcp_search_content` right after it was shown
  `mcp_tools_search_content`. In 161 runs of the local model
  `gpt-oss:20b` in gateway mode, 64 of the 78 calls to a name that does
  not exist were this one mistake. Up to 1.0.0-beta21 the answer was
  "Unknown tool. Did you mean ...?" and nothing was run, which cost a
  call each time. Now the tool is run, because no other tool can be
  meant. It is the same call as one by the full name: the same scope
  and permission checks, input checks and limits, and what the site
  records of the call names the real tool. It goes for a call by the
  short name itself, for a short name given to `mcp_tools_execute_tool`
  or `mcp_tools_get_tool_info` in gateway mode, and for the run tool
  called as `mcp_execute_tool`. The 23 tools named `mcp_tools_...` only
  read. Any other name that does not exist is answered with the closest
  names, as before, and nothing is run for it. That goes for
  `mcp_discover_tools` and `mcp_get_tool_info` too: no run had them.
  Measured with the same model, ten runs each way: 9 failed calls
  without the change and 4 with it, 119 of 120 tasks done both ways,
  and no change in the number of calls.
- **The server's instructions say where the Drupal version is.** They
  named the tools by area and ended with "config, cache and site
  health". Asked for the Drupal version, the local model `gpt-oss:20b`
  looked in config first in 13 of 40 runs, and 8 runs had a failed call
  (a config that is not there, a made-up tool). They now end with "site
  status (Drupal and PHP version, cron)". In 40 runs with that, the
  model went to the status tool every time: no failed call, and 2.5
  calls a run where it had been 3.6. The answer was right in all 80
  runs.
- **The instructions name the things the tools work on.** They said
  "structure (content types, fields, taxonomy, roles)", and the model
  searched for "structure", which finds 22 tools and not the content
  type ones. Now: "content types, fields, taxonomy, roles". Over 28 runs
  each way the tasks done were the same, with a few fewer calls and
  failed calls.
- **What breaks on Drupal 12 in our code is fixed.** Drupal 12 is not
  a supported version yet: no release of Tool API allows it. We ran our
  tests on Drupal 12.0.0-beta2 with the branch of Tool API's own Drupal
  12 merge request, and nothing failed in Tool API. In our code: three
  severity constants that are gone (the status tool, and the checks of
  the two transport modules), a file constant in the media upload, the
  Drupal AI bridge's `execute()`, and the body field. Drupal 12 moved
  the field type `text_with_summary` out of core, to a module of that
  name. On a site without it a new content type gets a long formatted
  text as its body, and a field asked for as `text_with_summary`
  becomes `text_long`, and the answer says so. All of it works on
  Drupal 10 and 11 as before.

### Documentation

- Six module READMEs and the use cases page listed 41 tool names that
  do not exist (`mcp_users_block_user` for `mcp_block_user`). A test
  now compares every tool name in the docs and in the code's messages
  with the tools.
- The README lists the submodules once, not three times, and its
  security part says what 1.0.0-beta21 does.
- The quick start's test command answered with an error. It uses
  `drush mcp:server-smoke` now.
- The examples no longer start the server with `--server=development`,
  which asks for a scope a new site does not allow.

## [1.0.0-beta21] - 2026-11-21

### Security

These tighten what a connection can do to accounts, roles and
permissions, and how the STDIO server gets its scopes. They are in
1.0.0-beta20, and the code is as old as the first releases, so assume
that every earlier release has them. If the account your MCP connection
runs as is not a full administrator, if you hand the write scope to an
agent you do not fully trust, or if you start the STDIO server with a
server profile, update.

- **A role cannot be given the permissions of MCP Tools itself, and a
  permission for trusted roles only needs the admin scope.** The role
  tools refused a list of 19 permissions by name. A connection with the
  write scope could give a role, its own too, the permissions that say
  which MCP tools a user may call, and permissions that Drupal marks
  "restrict access" and the list did not name. MCP Tools' own
  permissions are refused now, with every scope, next to the list. A
  "restrict access" permission is granted by a connection with the admin
  scope only. Even then it is not granted to the roles "anonymous" and
  "authenticated", and not to a role the connection's own user has,
  unless that user has the permission already. A person can still grant
  any of them on the permissions page.
- **A connection does not give its own user a role that raises it.**
  The user and batch tools refused administrator roles and gave out any
  other. So a connection could make a role with a strong permission and
  then take that role. For the user the connection runs as, a role that
  would add a permission is now left out, and the answer says why. Roles
  for other users are assigned as before.
- **The STDIO server gets no more scopes than the site allows.**
  `access.allowed_scopes` limited the scopes of an API key, a header and
  the environment. `--scope` on the command line and the scopes of a
  server profile were used as given. The shipped "development" profile
  has the admin scope, so `drush mcp-tools:serve --server=development`
  ran with the admin scope on a site that allows read and write only.
  Both are limited now, and the command says on stderr which scope it
  left out and how to allow it. To allow the admin scope, tick it under
  "Allowed scopes" on the settings form.
- **An administrator role is one by its flag, not only by its name.**
  The user tools refused roles called "administrator" or "admin". On a
  site whose administrator role has another name, that role could be
  given to a user, and its users could be blocked. The batch tool
  refused the exact name "administrator" only. They all go by Drupal's
  own flag now, and by the names as before.
- **`mcp_update_user` does not change an administrator.** It protected
  user 1 only, and changed the e-mail address, the status and the roles
  of other administrators. It refuses now, as `mcp_block_user` does.
  `mcp_activate_user` does not switch a blocked administrator on again.
- **An administrator role cannot be deleted.** The description of
  `mcp_structure_delete_role` said so. The code refused the anonymous
  and the authenticated role only.
- **The client's user is asked before the two strongest calls.** With
  the admin scope a connection grants a permission for trusted roles
  only, and it writes a recipe with any content and applies it in the
  next call. A recipe changes any config and grants any permission, past
  the rules of the other tools. Both ran without a question. Now the
  server asks the user of the client first, where the client can ask
  (MCP elicitation), as it does before a destructive tool. A no runs
  nothing. A recipe is asked about until a person has read it and
  deleted the file `UNREVIEWED.txt` in its folder.

Good to know before you hand out the admin scope: a client that cannot
ask gets these calls run, and the audit log says so with a warning. To
refuse them for such a client, tick "Strong calls need a confirmation"
on the settings form. Nobody is asked when a tool is called through MCP
Server or through the Drupal AI bridge. And a recipe that has run can
have changed any setting, the ones of MCP Tools among them. So give the
admin scope only to an agent you would let change any config of the
site.

### Fixed

- **`mcp_create_content` says whether the new item is published.** The
  `status` input was described as "Defaults to true". The code has
  always saved a draft when no status is given, and the result did not
  carry the status. A model that was asked for a published item left
  the input out, read "created successfully" and reported a published
  item. Nothing changes in what the tool does: without `status` it still
  saves a draft. The description now says so, the result has `status`
  ("published" or "draft"), and the message of a draft names
  `mcp_publish_content`.

- **`mcp_create_content` says when only the title was set.** Asked for
  an item with a body text, a model sent the title and no field values,
  read "created and published" and reported the body as set. The result
  now says "Only the title was set" and names the fields the content
  type has, with `mcp_update_content` to set them. The `fields` input
  shows a whole example, `{"body": "The text"}`: a text field takes a
  plain string.
- **A text is saved with a format the site has.** A plain string for a
  text field was saved with the format `basic_html`, on every site. A
  site without that format (the minimal profile, or a site with formats
  of its own) stored the text and showed nothing: Drupal renders a text
  whose format is missing as empty. The same happened to a format a
  model named because our descriptions show it. Now: the format that
  was asked for if the site has it, else `basic_html` if the site has
  that, else the site's fallback format. A site with `basic_html` sees
  no change. This covers content, batch, migration and translation
  tools and term descriptions.
- **`mcp_update_user` takes `mail`, as its description says.** The code
  read `email` only, so a change sent as described did nothing and was
  reported as done. Both are taken now, and a field the tool does not
  change is refused. `mcp_assign_user_roles` says in its answer which
  roles it left out and why.
- **`mcp_list_text_formats` says who may use a format.** It read the
  roles from a property that Drupal leaves empty once a format is
  saved. So every format had no roles, and `default_format` was
  "plain_text" on every site. The roles now come from the permissions,
  and the default is the first format an anonymous user may use.
- **The server's instructions no longer promise a confirm flag for every
  deleting tool.** They said "Deleting asks for an explicit confirm
  flag". Of the 18 deleting tools one has such an input. A model that
  had read the sentence passed `confirm` to `mcp_delete_view` ten times
  in one run and was refused each time. The sentence now says to pass
  one only when the tool has such an input.
- **`mcp_tools_ai`: an argument a model got wrong is answered.** Drupal
  AI reads a model's arguments into the function call before it runs
  it, and throws for a value it cannot read: a word where an object
  belongs, or JSON with a brace too many. Nothing caught that, so the
  whole agent run ended with an exception. The model now gets an answer
  that names the input and the problem, and can call the tool again.
  Nothing is run for such a call. `mcp_tools_ai` is experimental.

### Changed

- **A recipe written through MCP is marked, and the site says when it
  was applied unread.** A recipe that `mcp_recipes_create` writes gets a
  file `UNREVIEWED.txt` in its folder. While the file is there, the
  audit lines of each apply name the recipe as unreviewed, also when
  another recipe pulls it in, the answer says so, and Drupal's status
  report lists the recipe. Read `recipe.yml` and delete the file once
  you have. Recipes you put on the server yourself have no such file.
- **The audit line for a recipe is written before the recipe runs.** It
  was written after. A recipe changes any config, so one that switched
  `access.audit_logging` off left no line of its own run. Now a line
  `apply_recipe_start` comes first, and when a recipe switched the audit
  log off, the answer says so and a warning goes to the site's log.
- **The audit log says whether a person said yes.** A line for a call
  the client's user confirmed carries `confirmed_by_user`. A grant of a
  permission for trusted roles only and an apply of an unreviewed recipe
  are written as warnings when nobody confirmed them.
- **The setting "Destructive tools of other modules need a
  confirmation" is now "Strong calls need a confirmation".** It covers
  the two calls above too. And it says what the server has done since
  1.0.0-beta20: every tool that declares itself destructive is asked
  about, MCP Tools' own tools too. The code meant to leave those out and
  never did.
- **A tool name that does not exist is answered with the closest real
  names.** A model often makes a name up from what it expects: the module
  left out (`mcp_list_content_types`), the words the other way round
  (`mcp_config_get`), a letter missing. "Unknown tool" alone sent it
  searching again. The answer now says "Did you mean
  mcp_tools_list_content_types?", and in gateway mode it also names the
  tool to search with. Only tools the connection may call are offered.
  Nothing is run for the model: it gets names and calls the one it meant.
  A name close to one of the gateway's own three tools
  (`mcp_execute_tool`) gets that tool.
- **Descriptions name other tools by their real name.** 114 tools said
  things like "Use ListContentTypes to see available types". That is the
  name of the PHP class. A client only sees tool names, so a model made
  one up from it (`mcp_list_content_types`). The descriptions now say
  `mcp_structure_list_content_types`. The module's help page lists the
  base tools by their real names too, and no longer carries tool counts
  that had gone stale. Twelve descriptions no longer end in "This is a
  write operation": the tool's hints already say so.
- **Gateway mode: a found tool comes with the names of its inputs.** A
  model that has found a tool often calls it at once and guesses the
  argument names ("title" where the search tool takes "query"). In the
  runs of five local models that was half of all failed calls. Each match
  of `mcp_tools_discover_tools` now has `inputs`, with the required and
  the optional names. `mcp_tools_get_tool_info` still has the full schema.
- **Gateway mode: the best match comes first.** Matches came in the
  order of the catalog. A model that asked for "view" with a limit of ten
  got the config tool first ("View a specific configuration object") and
  did not see the tool that makes a view of a content type. Tools with
  the search words in their name now come first, then the ones with the
  words in their label, then the rest. Nothing is dropped.
- **Gateway mode: a plural finds what its singular finds.** The tools
  are named `..._view`, `..._user` and `..._content_type`, and a model
  asks for "views", "users" and "content types". A plural found only
  the tools that happen to have it in a description, in catalog order.
  It now finds and orders what the singular does.
- **A search for "health" finds the site status tool.** The server's
  own instructions call that group of tools "site health", and a model
  that searched for the word found the cron tool only.
- **Gateway mode: one of the gateway's own tools, named in
  `mcp_tools_execute_tool`, gets a plain answer.** It said "Unknown tool"
  and advised the tool that had just been named.

## [1.0.0-beta20] - 2026-11-21

### Added

- **`mcp_tools_policy`: the rules of MCP Tools on MCP Server's HTTP
  endpoint (experimental).** For a site that serves tools through MCP
  Server 2.x and its Tool Bridge. MCP Server runs a Tool API tool for
  any user who has the tool's permission. With this submodule on, a
  tool of any module that is called there also answers to the default
  scopes, read-only mode and config-only mode, counts against the write
  limits, and leaves an audit line. A refused call does not reach MCP
  Server: the client gets a tool result that says "Access denied", or
  the limit and how many seconds to wait. It is a middleware in the
  place MCP Server documents for such rules. Not covered: MCP Server
  over STDIO, a confirmation before destructive tools, and callers
  other than MCP Server. Its README lists what else it cannot see.
  Tested with the official JS client against MCP Server 2.0.0-beta7 and
  Tool Bridge 1.0.0-beta5.

### Changed

- **The tool list is what the connection may call.** Until now every
  tool was listed and a call was refused at the door: a read-only key
  was shown more than a hundred write tools it could never run. The list
  now follows the same rule as the door: the scope the tool's operation
  needs, read-only mode, config-only mode and the category permission of
  the user. The server's instructions tell the model how many tools are
  not listed, and why. In gateway mode, discovery leaves the same tools
  out. A tool that is not listed is still refused with "Access denied"
  when called by name. The STDIO server builds its list at start.
  To get the whole list back: `access.list_all_tools` (a checkbox on the
  settings form).
- **Tools of other modules are rate limited and audited.** With "all
  tools" on, the server runs Tool API tools of other modules. Scopes and
  modes already held for them. Now a write or trigger tool of another
  module also counts against the write limits (a tool that declares
  itself destructive counts as a delete), and each one that ran has a
  line in the audit log.
- **Gateway mode: the "run a tool" tool says read-only when the
  connection can only read.** It can run every tool, so it was always
  described as one that may change the site. A client that asks its
  user before such a call asked before every call in gateway mode, also
  for a read, and refused them all when nobody was there to ask (seen
  with Codex CLI). A connection without the write and admin scopes
  cannot change anything through it, and no connection can while the
  site is in read-only mode: its hints now say so.
- **Gateway mode: a tool can be called by its own name.** Gateway mode
  lists three tools: find, describe, run. A model that has found a tool
  often calls it by the name it found, and not through the run tool. The
  server answered "Tool not found", and a small model tried the same call
  again until it gave up. Such a call now runs, through the same checks
  as every other call: access, scopes, limits and the audit log.
  Arguments sent in the run tool's shape (`tool_name` and `arguments`)
  are accepted too. The tool list still shows the three tools.

- **The site status tool says what it has.** Its description now names
  the Drupal core version and the PHP version, and the gateway's overview
  points to it. "Which version of Drupal core does this site run" was the
  question a local model failed most: it searched "core" and never found
  the tool.

- **Gateway mode: the tool search finds a tool by a question in the
  model's own words.** It took the whole query as one piece of text, so
  "node type" found nothing and a local model gave up on a task that
  the content type tools would have done. When the phrase is nowhere,
  the words are now tried one by one: first the tools that have all of
  them, then the ones that have some. An answer with no tools says so
  and names the groups there are.
- **A destructive tool of another module asks first.** When the client
  can ask its user (MCP elicitation), a tool of another module that
  declares itself destructive runs after the user said yes. A no runs
  nothing, uses nothing of the limits and leaves no audit line. A client
  that cannot ask gets the tool run, as before; with
  `access.confirm_destructive_tools` (a checkbox on the settings form)
  it is refused. Only what a tool declares counts: Tool API also calls
  every undeclared write tool destructive. MCP Tools' own tools keep
  their confirm flags.

- **Tools run through Tool API's runner. Requires Tool API 1.0.0-beta12
  or later.** The server and the Drupal AI bridge used to drive a tool
  by hand: make it, set its inputs, ask it for access, execute it. Tool
  API has one sequence for that since beta12 (`tool.runner`), and its
  plan is to remove the by-hand methods before its 1.0.0. What changes
  for a tool: it is told who called it (`mcp_tools`, or `mcp_tools_ai`
  from the AI bridge), a permission it declares is checked before it is
  made, and Tool API checks the inputs before it asks the tool for
  access. What a client gets back has the same shape as before. Composer
  updates Tool API with the module. The new floor also settles the
  hints: on Tool API beta9 a write tool that declares nothing looks the
  same as one that declares itself harmless, so it could not be sent as
  destructive.
- **The rules for other modules' tools are one piece.** Scopes,
  read-only mode, config-only mode, the write limits and the audit line
  were written twice, in the server and in the AI bridge. They are one
  service now, `mcp_tools.tool_policy`, that knows Tool API and nothing
  of MCP. It holds for calls through MCP Tools: the server, the gateway
  and the AI bridge. The new `mcp_tools_policy` submodule puts it in
  front of MCP Server's HTTP endpoint. A call that reaches a tool
  another way (ECA, code of the site) does not pass through it, because
  Tool API has no place yet where a rule can refuse a call for every
  caller.

- **Docs: Codex CLI, local models, and the MCP Server 2.x section.**
  `docs/CLIENT_INTEGRATIONS.md` has the line that adds the server to
  Codex CLI, tried with Codex CLI 0.166.0 (it lists the tools, reads
  and writes, and asks you before a tool that can change the site). It
  also says how to use a model on your own machine (gateway mode) and
  what we measured with two local models under Ollama. The
  MCP Server 2.x section had three stale lines: the install command,
  the endpoint (`/mcp`, not `/_mcp`), and a note that the bridge drops
  unknown arguments, which it no longer does.

### Removed

- **Webhook notifications, which never sent anything.** The settings
  form, the config and the documentation offered them since the first
  release, but nothing called the service that would send one. The
  service, its form section and its settings are gone. An update removes
  the old `webhooks` block from `mcp_tools.settings`, the signing secret
  with it: run `drush updb`. To get notified of tool calls, subscribe to
  the tool execution events, as the `mcp_tools_observability` module
  does.

- **`drush mcp:scaffold`, `hook_mcp_tools_components()` and the profile
  setting `component_public_only`.** The hook was documented as a way to
  add tools without Tool API and was never invoked, so a scaffolded
  module added nothing, and its "service" variant named an interface
  that does not exist. The switch that went with it did nothing. All
  three are gone, and the Quickstart no longer has that step. To add
  tools, write a Tool API plugin. An update removes the setting from the
  server profiles: run `drush updb`.

### Fixed

- **Enabling a transport in the same step as the base module stopped
  with "Class not found".** In 1.0.0-beta19, `drush en mcp_tools
  mcp_tools_stdio` in one command, or `drush en mcp_tools_stdio` or
  `drush en mcp_tools_remote` on a site without `mcp_tools`, failed with
  `Class "Drupal\mcp_tools\Mcp\Sdk" not found`. Nothing was changed
  when that happened, and enabling `mcp_tools` first, as the docs say,
  worked. The transports check for the MCP SDK when they are installed,
  and since beta19 that check used a class of the base module, which
  cannot be loaded in the same step. It can now. Before a release the
  modules are now enabled in six ways on a new site, where the tests
  only enabled them one after the other.

- **`mcp_tools_ai`: no tool call of a real model reached a tool.** A
  model answers with a function name. Drupal AI looks that name up and
  makes the plugin by the ID in its definition, and ours all carried the
  base ID. So every call ended in "The mcp_tools_tool plugin does not
  exist". The module's tests made the function calls by their full ID
  and never took that path. It showed with the first run of a real
  model through AI Agents. Each definition now carries its own ID.

- **The gateway's tool provider ran a tool without its inputs and without
  an access check, and reported success.** This is
  `DrupalToolProvider::execute()`, a method for code that uses the
  gateway library's own classes. The server never called it: gateway
  calls went through the call handler, which checked both. It runs
  through the same path as every other call now.

- **A server profile with a name of your own had no config schema.**
  Only `default`, `development`, `staging` and `production` were
  described, so strict config validation refused any other profile. The
  profiles are a list keyed by name now.
- The audit log called every call through drush "http", the STDIO
  server included. It says "cli" for those now.
- **Hints for tools of other modules.** A tool was called read-only only
  when its operation was "read". Tool API has two more operations that
  change nothing, explain and transform; other modules' tools that use
  them were advertised as tools that may modify the site (15 of Tool
  Belt's). And a write tool that declares it destroys nothing was sent
  without a destructive hint, which a client reads as destructive. Both
  hints now say what the tool declares.

## [1.0.0-beta19] - 2026-11-20

### Security

- **A default Composer install is clean again**
  ([#3613061](https://git.drupalcode.org/project/mcp_tools/-/work_items/3613061)).
  Three things kept the module on packages with advisories, and recent
  Composer versions refuse to install those by default:
  - mcp/sdk was capped at 0.6, and code-wheel/mcp-tool-gateway 1.2.0
    pinned exactly 0.6.0. CVE-2026-53965 affects 0.5.0 up to 0.7.0. It is
    in the SDK's client code, which this module does not use. mcp/sdk
    0.7.1, 0.9 and 0.10 are now allowed and the gateway is required at
    ^1.2.1.
  - enshrined/svg-sanitize was capped at 0.22.0, which has three
    advisories (CVE-2026-107379, CVE-2026-107380, CVE-2026-107381). 1.x is
    now allowed. It sanitizes SVGs fetched by `mcp_tools_remote_media`.
  - mcp/sdk 0.8 has an advisory of its own (fixed in 0.9.0), so it is
    skipped.

  A fresh install resolves to mcp/sdk 0.10.0 and svg-sanitize 1.1.1, and
  `composer audit` reports nothing. Run `composer update drupal/mcp_tools -W`.
- **`mcp_tools_ai` ran tools without the access check.** The bridge to
  Drupal AI called the tool directly, and neither Drupal AI nor Tool API
  checks access at that point. A user who could reach an AI assistant or
  agent with these tools could run the exposed ones (read tools by default)
  without the tool's `mcp_tools use ...` permission. The bridge now checks
  access first and refuses with no data.
- **Resources and the site brief prompt had no access check.** Only tools
  were gated. `drupal://site/status`, `drupal://site/snapshot`,
  `drupal://system/requirements`, `drupal://config/{name}`,
  `drupal://node/{nid}` and the `mcp_tools/site-brief` prompt now need the
  read scope and the permission of the matching tool category
  (`site_health`, `config`, `content`). In the snapshot, the content
  types, role names and changed config names need the `structure`, `users`
  and `config` permissions.
- **Other modules' tools skipped scopes, read-only mode and config-only
  mode.** With "all tools" on (`--all-tools`, or `include_all_tools` in a
  server profile) the server also runs Tool API tools of other providers.
  Those three checks sat in MCP Tools' own tool base class, so such a tool
  ran without them: on a site with Tool Belt, a session with
  `--scope=read` could create a node. The server now applies the same
  rule to them, by the operation a tool declares. `mcp_tools_ai` had the
  same gap and has the same fix. "All tools" is off by default.
- **Three tools that change the site were declared as read
  operations:** `mcp_block_user`, `mcp_activate_user` and
  `mcp_layout_allow_custom`. The server told clients they change nothing,
  and some clients run such tools without asking. Config-only mode did
  not stop the two user tools: with that mode on, a session with the
  write scope could still block or activate an account. A read-only
  session was refused all along, by a second check. All three are write
  operations now.

### Added

- **The 2026-07-28 revision of MCP**, with mcp/sdk 0.9 or 0.10. That revision has
  no handshake and no sessions: every request stands alone. The same
  `drush mcp-tools:serve` command and the same HTTP URL now answer both
  kinds of client:
  - `server/discover` tells a client what the server supports.
  - Lists say how long a client may keep them: five minutes, and only
    for the client that asked.
  - The delete confirmation comes back as an "input required" result, and
    the client answers on a retry of the call.
  - Progress for bulk calls arrives with the call's own response.
  - Over HTTP no session is created, and API key scopes apply to each
    request.

  Clients on the older revisions keep working as before. The server still
  runs on mcp/sdk 0.6 and 0.7.1, where it speaks the older revisions only.
- **MCP Tools installs next to MCP Server 2.0.0-beta7 and later**, which
  require mcp/sdk 0.9.
- **mcp/sdk 0.10.** It renames the SDK's server class, keeps nested JSON
  objects in tool arguments as objects, and fails a request when Drupal
  suspends its Fiber. All three are handled; the module runs on 0.6, 0.7.1,
  0.9 and 0.10 from one code base.
- **Browser-based clients on an allowed origin keep working on mcp/sdk
  0.9.** That SDK version compares the `Origin` header with a list of its
  own; the `allowed_origins` setting keeps deciding.

### Fixed

- **Tools that failed on every call, or on every real site.** Their unit
  tests mocked the Drupal classes and stopped at the refusals. Each now
  runs for real in a test:
  - `mcp_theme_enable` and `mcp_theme_disable` called methods the theme
    handler lost in Drupal 9.
  - `mcp_add_view_display` called a method the view executable does not
    have. The title and page size it sets are now used by Views.
  - `mcp_redirect_create`, `_get`, `_find`, `_list` and `_update` failed as
    soon as one redirect existed, and a create failed after it had saved
    the redirect. They asked the redirect for a hit count it does not
    keep. `count` is gone from the output of `mcp_redirect_get`.
  - `mcp_pathauto_create` failed for a pattern limited to one bundle,
    `mcp_pathauto_update` saved a pattern Pathauto could not read, and the
    bundles of patterns made in Pathauto's own form were listed wrong. A
    pattern for one bundle now gets weight -5, as in Pathauto's form.
  - `mcp_moderation_get_content_by_state` failed on every site with a
    workflow. Content now counts under the state of its newest revision.
  - `mcp_layout_add_block` and `mcp_layout_remove_block` failed on
    Drupal 10: they called a method that is only public from Drupal 11.
  - `mcp_cache_rebuild` failed for the type `theme` on Drupal 11: it
    called a function that Drupal 11 removed. The other three types
    worked.
  - `mcp_create_view` and `mcp_create_content_list_view` made views that
    were not what was asked for. The sort values the tools document
    (`newest`, `oldest`, `title`, `changed`) were not understood, so
    every view sorted newest first. A view of taxonomy terms failed on
    its first query. A field of the content type, such as `body`, gave a
    view that could not be shown. All of these work now. A sort or a
    field the view cannot have is refused, and nothing is created. The
    `show_title` input of the content list did nothing: the title now
    leads the list as a link, or is left out when it is switched off.
  - `mcp_structure_add_field` and `mcp_paragraphs_add_field` crashed on a
    list of numbers (`allowed_values: [1, 2]`).
  - `mcp_structure_list_field_types` offered field types the site cannot
    create because their module is off, such as list fields without
    Options. It now lists the types the site has, and adding another is
    refused with that reason.
  - `mcp_create_media_type` told clients to pass `audio` or `video`.
    Drupal calls these sources `audio_file` and `video_file`, so both
    failed. The description names the real ones, and the short names
    are accepted too.
- **`mcp_scheduler_publish` and `mcp_scheduler_unpublish` answered
  "scheduled" for content Scheduler would never touch.** Scheduler only
  acts on content types that have scheduling switched on. Both tools now
  refuse the others, and `mcp_scheduler_get_schedule` reports
  `scheduling_enabled` truthfully.
- **The batch tools answered "success" when every item failed.** The six
  batch tools and `mcp_redirect_import` listed the errors inside a result
  that said success. A batch in which nothing could be done is now a
  failure that names the first error. A batch in which some items worked
  is still a success with its errors listed.
- **A user was asked to confirm a deletion that was then refused.** With a
  client that can ask its user, `mcp_delete_content` put the question
  before the access check. A session that may not delete is now refused
  without a question.
- **`mcp_create_content` dropped unknown fields without a word** and
  reported success. It now names them and creates nothing, as
  `mcp_update_content` already did.
- **In a long STDIO session a field added elsewhere was unknown.** The
  server kept the field list of a content type as first read. A field an
  admin added in the UI did not show, and a value sent for it was
  dropped. Field lists are now read fresh on each call.
- **`mcp_analysis_performance` returned empty table names on MySQL 8**,
  with a PHP warning for each row. MariaDB was not affected.
- **`drush mcp-tools:client-config` named a drush that does not exist** on
  a site built from `drupal/recommended-project`: it looked for `vendor`
  inside the web root. A client given that config could not start the
  server.
- **Saving the remote endpoint's settings form removed the execution
  user**, and the form could not enable the endpoint: it always answered
  "An execution user is required". Sites set up with drush were not
  affected until someone saved the form. "Use site admin (uid 1)" is now
  ticked only when uid 1 is the user in use.
- **Clients built on the official JS SDK did not get our error
  messages.** That client checks every result against the tool's
  `outputSchema`, error results included, and replaces a result that does
  not match with "Structured content does not match the tool's output
  schema". Three kinds of result failed the check. Error results (bad
  input, access denied, exceptions) had `error` but not the `message` the
  schema requires; they carry both now. `mcp_tools_get_site_status`
  returned `maintenance_mode` as 0 or 1 once core's maintenance form had
  been saved. An empty map went out as `[]` (`mcp_tools_get_files` on a
  site with no files); it goes out as `{}`.
- **System status had no core checks on Drupal 11.2 and later.** Drupal
  11.2 moved its runtime checks to `hook_runtime_requirements()`, which
  the service did not call. On a Drupal 11.4 test site
  `mcp_tools_get_system_status` reported 1 check and no errors where the
  status report page had 29 checks and 2 errors. The requirements and
  snapshot resources and the site brief prompt read the same data.
- **`drush mcp-tools:serve --server=<profile>` ignored the profile's
  `gateway_mode` and `include_all_tools`.** The shipped staging and
  production profiles turn gateway mode on; the server started with the
  full tool list.
- **In a long STDIO session, changes made elsewhere took too long to
  show.** A mode switched on in the settings took effect one call late.
  A permission removed from the user's role stayed in force until the
  server was restarted. A node read as a resource stayed as it was until
  some tool call reset it. All three are read fresh on the next call.
- **Progress updates and sampling stopped after `mcp_cache_clear_all`** in
  a STDIO session.
- **Gateway discovery pointed at `get-tool-info`**, a tool name that no
  longer exists.
- **`mcp_translate_content` could overwrite the source text of
  paragraphs.** It assumed each language has its own copy of the node's
  paragraphs, as Layout Paragraphs makes when the paragraph field is
  translatable. In the usual Paragraphs setup the languages share the
  paragraphs, and the translated text was written over the source text
  with no translation created. `mcp_update_content` with `language` did the
  same to a shared paragraph that had no translation yet. Shared
  paragraphs now get their own translation. Sites with per-language copies
  are not affected. **If you used these tools on shared paragraphs since
  1.0.0-beta16, check the source text of those paragraphs.**
- **`mcp_paragraphs_list_types` and `mcp_paragraphs_get_type` failed** for
  every paragraph type without an icon.
- **Tools failed on Tool API 1.0.0-beta10 and later.** Tool API now checks
  every result against the tool's declared outputs and fails the call when
  a required output is missing. MCP Tools results only fill the outputs
  that apply, so tools like `mcp_cache_clear_all`,
  `mcp_tools_check_cron_status` and `mcp_cron_get_status` failed after they
  had already run. All MCP Tools outputs are now optional, as the
  advertised outputSchema already said; values that are set are still
  type-checked.
- **`mcp_get_translatable_content` failed on every call** with Tool API
  1.0.0-beta10 and later: `existing_translations` was declared as a string
  but is a list
  ([#3613068](https://git.drupalcode.org/project/mcp_tools/-/work_items/3613068),
  patch by julien).
- **`mcp_metatag_set_entity` reported success but saved nothing.** It
  picked Metatag's read-only computed `metatag` field and stored the tags in
  the wrong shape. It now finds the metatag field by type and stores the
  tags the way Metatag does
  ([#3613060](https://git.drupalcode.org/project/mcp_tools/-/work_items/3613060),
  fix by longwave).
- **The SDK filled the site logs.** It logged every registered tool,
  resource and prompt at debug level on each request (177 watchdog rows
  for one short session on the reporter's site). The SDK now only logs
  warnings and above. On Drupal 11 it also logged a warning on most HTTP
  tool calls, because Drupal pauses and resumes work in a way the SDK did
  not expect; those are gone too
  ([#3613065](https://git.drupalcode.org/project/mcp_tools/-/work_items/3613065),
  patch by julien).
- **Tool messages showed up as strings to translate.** Every distinct
  result message was stored in `locales_source`. Messages now use one fixed
  source string; clients get the same text as before
  ([#3613064](https://git.drupalcode.org/project/mcp_tools/-/work_items/3613064),
  patch by julien).
- **Stale State in the STDIO server.** `drush mcp-tools:serve` kept the
  State values from its first read, so `check_cron_status` reported cron
  runs from hours ago. Each tool call now starts from fresh State
  ([#3613067](https://git.drupalcode.org/project/mcp_tools/-/work_items/3613067)).
- **The STDIO server lost its scopes after `mcp_cache_clear_all`.** That
  tool rebuilds Drupal's container inside the running server, which
  replaced the service holding the session's scopes. A session started
  with `--scope=read,write` then refused every later write until it was
  restarted.
- **Calling another module's tool with a list argument crashed** in
  `--all-tools` mode, the same way listing such tools did.
- **Stale config and content in the STDIO server.** The same long-running
  process also kept config objects, loaded entities and cache tag counts
  from its first read. A site name or a node title changed by an editor
  stayed stale until the client restarted the server. Each tool call now
  starts from fresh copies; the caches all requests share are left alone.
- **`--all-tools` stopped the server from starting** when another module's
  tool declared a list input (Tool Belt does). MCP Tools can now serve
  other modules' Tool API tools next to its own.
- **Validation errors had unfilled blanks**, such as "The required
  properties ({missing}) are missing". They now name the property and the
  problem: "nid: The data (string) must match the type: integer".
- **Gateway mode rejected a tool call with an empty `arguments` object**,
  which is what most clients send for a tool with no inputs.
- **Write tools could report failure after they had worked.**
  `mcp_update_content` returned `revision_id` as a string where it declares
  an integer, and `mcp_create_content` could do the same with `nid`. A
  client that checks results against the tool's output schema rejects
  that, and so does mcp/sdk 0.9: through MCP Server 2.0.0-beta7 an update
  changed the node and then answered "Invalid structured output". Tools
  now return scalar outputs in the type they declare.
- **`mcp_tools_get_site_status` reported 0 installed modules** on every
  site.
- **System status printed "Array"** for status report entries that
  modules give as render arrays (Drupal 10's update status is one), with a
  PHP warning each time. They are rendered to plain text now. This also
  covers the `drupal://system/requirements` and `drupal://site/snapshot`
  resources and the site brief prompt.
- **`mcp_cache_get_status` listed cache backends as cache bins**
  ("backend.memory", "backend.apcu" and so on) and failed on SQLite. It
  lists the site's real cache bins now.
- **`mcp_analysis_performance` failed on every database but MySQL and
  MariaDB.** Its table size query is MySQL's. Other databases now get the
  rest of the analysis and a note that table sizes are not read there.
  Tested on SQLite and PostgreSQL 16.
- **Error text from an exception reached MCP clients HTML-escaped**
  (`&quot;` for a quote). It arrives as thrown now.
- **The module files said Drupal `^10.3 || ^11 || ^12`.** Tool API, which
  this module needs, supports `^10.5 || ^11`, so that is what they say now.
  Composer already enforced it. Drupal 12 follows when Tool API supports it.
- **The "mcp/sdk is missing" messages told you to install mcp/sdk 0.2**,
  the oldest line. They name the current one.
- **`mcp_tools_get_system_status` failed** when any module reported a
  requirement's severity as the `RequirementSeverity` enum that Drupal 11.2
  added. Drupal 12 only has the enum. The `drupal://system/requirements`
  resource and the site brief prompt use the same code.
- **The server advertised features it does not have** (logging,
  completions, resource subscriptions, list-changed notifications). It now
  declares tools, plus resources and prompts when it has any.
- Two refusal messages told the caller to pass `language: '<code>'`.
  Clients that render messages as HTML dropped the tag. They now spell it
  out.
- **`mcp_structure_scaffold_content_type` always failed** with a
  TypeError (MR !6 by ggerard).
- **Tools whose inputs are all optional rejected a call with no
  arguments** (MR !7 by ggerard).
- The field type list returned "Array" as the description of some field
  types.
- **An empty string for an optional filter matched nothing.** Clients
  often send `""` instead of leaving a field out; `content_type`, `bundle`,
  `role`, `theme`, `prefix` and similar filters now treat it as "no filter"
  (MR !5 by ggerard).
- **`mcp_tools_sitemap` was uninstallable with simple_sitemap >= 4.2**:
  the service depended on the removed `simple_sitemap.manager`
  (SitemapManager) API. Ported to the current API: SimpleSitemap config
  entities for variants, the entity manager for bundle settings, the
  queue worker for queue status, and `setSitemaps()->rebuildQueue()->
  generate()` for regeneration.

### Changed

- **The three gateway tools have new names:** `mcp_tools_discover_tools`,
  `mcp_tools_get_tool_info` and `mcp_tools_execute_tool`. The old names
  (`mcp_tools/discover-tools` and so on) contained a slash, which the MCP
  tool naming rule does not allow. Update any client setting or prompt
  that names them.
- **The server now sends instructions** with `initialize`: what the tools
  cover, how scopes and delete confirmation work, and how to use gateway
  mode. Some clients load only this and the tool names at first.
- **Requires Tool API 1.0.0-beta9 or later, and so Drupal 10.5 or
  later.** Tool outputs are declared with Tool API's `OutputDefinition`
  instead of core's `ContextDefinition`, which is deprecated in tool
  1.0.0-beta9 and removed in 1.0.0-rc1. With every submodule enabled this
  removes one deprecation notice for each of the 963 outputs on every
  plugin cache rebuild
  ([#3613062](https://git.drupalcode.org/project/mcp_tools/-/work_items/3613062)).
- **Converted tool schemas are cached persistently** (keyed by plugin
  ID, cleared on `drush cr`). The stateless HTTP transport rebuilds the
  server per request; caching cuts the schema-conversion portion of that
  build from ~700 ms to ~5 ms at ~190 tools, also speeding STDIO boot.
- Gateway responses use compact JSON (~30% smaller).
- **Gateway discovery without a query returns a compact overview**:
  provider groups with counts and example tool names (~460 tokens)
  instead of the full catalog (~5,700 tokens at 32 tools), with a hint
  to query. Query-scoped discovery gains a `limit` parameter (default
  25, max 100) and reports `total_matches` with a truncation hint.
  Matching now also covers the provider name.

## [1.0.0-beta18] - 2026-07-24

### Added

- **`mcp_generate_alt_text` (sampling).** Generates image alt text with the
  connected MCP client's own model: the image is sent to the client via an
  MCP sampling request, the description comes back, and it is stored as the
  image field's alt text (audit-logged; refuses politely on clients without
  the sampling capability). 227 tools total.
- **Parameterized resource templates.** `drupal://node/{nid}` (read-only
  node summary honoring view access) and `drupal://config/{name}` (through
  the redacting config reader) join the static resources.
- **Progress notifications for bulk operations.** When the client attaches
  a progressToken to a tool call, the batch tools (create/update/delete
  multiple) stream per-item MCP progress notifications. Implemented via a
  new request-scoped `mcp_tools.client_bridge` service the call handler
  arms during execution. Services report progress unconditionally and it
  no-ops outside an MCP request (cron, drush, other Tool API runtimes).

## [1.0.0-beta17] - 2026-07-24

### Added

- **Native user confirmation for destructive deletes (elicitation).** When
  the MCP client supports elicitation, a bare `mcp_delete_content` call asks
  the actual human through the client's own UI instead of returning the
  refusal round-trip: accept proceeds as a confirmed full delete, decline
  cancels cleanly. Clients without the capability keep the beta15
  refusal-based guardrail unchanged. Requires mcp/sdk >= 0.6 and a
  streaming transport.
- **`outputSchema` in `tools/list`.** All tools now advertise their typed
  structured-output envelope ({success, message, data}) built from their
  Tool API output definitions.
- The weekly Bridge Compat job now registers **every** available Tool API
  tool through mcp_server_tool_bridge and requires every derivative and
  generated schema to materialize.

## [1.0.0-beta16] - 2026-07-24

### Added

- **`mcp_tools_translate` submodule**: content translation workflows
  ([#3611087](https://www.drupal.org/project/mcp_tools/issues/3611087),
  patch by julien). Three tools: `mcp_get_translatable_content` (all
  translatable text from a node and its paragraphs, in reading order),
  `mcp_get_translation_status`, and `mcp_translate_content` (creates a
  complete linked translation; incomplete payloads are rejected before
  anything is saved). `mcp_update_content` with `language` now updates a
  single existing translation, including a `paragraphs` map keyed by
  source paragraph ID; non-translatable (shared) fields and unknown
  fields are rejected. Gated by Drupal's per-bundle content translation
  permissions plus a new `mcp_tools use translation` permission.
  Adjustments to the contributed patch: paragraph reference fields are
  discovered dynamically instead of hardcoded, translations keep the
  source field's text format instead of a hardcoded one, the paragraphs
  module is now a soft dependency (plain node translation works without
  it), and the per-language update path (`updateTranslation()`) was
  completed. Kernel-tested with and without paragraphs.

### Fixed

- **`mcp_tools_paragraphs` fataled on paragraphs >= 1.18**: the service
  called `ParagraphsType::getIconUuid()`, which current paragraphs
  releases do not provide. Now uses `getIconFile()?->uuid()`.

- **Tools with conditional outputs broke external Tool API consumers.**
  Declared outputs a result did not populate (e.g. `language` on a full-node
  delete, the translate-only outputs on `mcp_update_content`) made Tool
  API's `getOutputValues()` throw after the tool had already executed,
  surfacing as an error response from
  [mcp_server_tool_bridge](https://www.drupal.org/project/mcp_server_tool_bridge)
  even though the operation succeeded. `McpToolsToolBase` now backfills
  unset declared outputs with NULL and conditional outputs are marked
  optional. mcp_tools' own transports were unaffected (they fall back to
  raw context values). Found by running the content tools end-to-end
  through mcp_server 2.x + mcp_server_tool_bridge.

## [1.0.0-beta15] - 2026-07-24

### Changed

- **BREAKING: `mcp_delete_content` requires explicit confirmation**
  ([#3612659](https://www.drupal.org/project/mcp_tools/issues/3612659),
  patch by julien). Deleting a node now requires `confirm_delete_all: true`;
  a bare `{nid}` call is rejected with a list of the node's language
  versions. A new optional `language` parameter deletes a single translation
  instead, with source-language protection and a new revision as a recovery
  path. When the Trash module soft-deletes nodes, the result message says the
  node is restorable.
- **Unknown tool parameters are now rejected**
  ([#3612659](https://www.drupal.org/project/mcp_tools/issues/3612659),
  patch by julien). All generated tool schemas declare
  `additionalProperties: false`, and validation errors list the allowed
  parameters. Previously an undeclared key passed validation and was silently
  dropped: a `mcp_delete_content {nid, language}` call fell through to a
  destructive full-node delete instead of failing loudly. The same policy
  now covers tools with no declared inputs (stray arguments are rejected
  rather than skipped past validation) and the gateway meta-tools
  (`mcp_discover_tools`, `mcp_get_tool_info`, `mcp_execute_tool`).
- `mcp_update_content` rejects unknown field names before saving and reports
  the affected language and updated fields. Passing `language` explains that
  per-language updates need the upcoming `mcp_tools_translate` submodule
  ([#3611087](https://www.drupal.org/project/mcp_tools/issues/3611087)).
- **Requires drupal/tool >= 1.0.0-beta2** (constraint was `^1.0@alpha`).
  Tool API alpha9's normalizers fatal on Drupal core 11.4 (its
  `SchematicNormalizerTrait::normalize()` signature changed). Composer's
  prefer-stable already resolves beta2 on fresh installs; the constraint
  now rules out the broken alphas for everyone.

### Fixed

- **Config deployments broke on `mcp_tools_servers.settings`**
  ([#3613059](https://www.drupal.org/project/mcp_tools/issues/3613059)).
  Simple configuration must be prefixed with the owning module's name; the
  old name declared a dependency on a nonexistent `mcp_tools_servers`
  extension, so `drush cim` refused to import. The config object is now
  `mcp_tools.servers` and an update hook migrates existing sites.
- **Remote SSE streams executed tools as the anonymous user**
  ([#3611082](https://www.drupal.org/project/mcp_tools/issues/3611082),
  patch by julien). The execution account was switched back before a
  streamed (SSE) response ran the MCP request loop; the streaming callback
  now re-applies the configured account.
- **Public remote deployments were rejected with "Forbidden: Invalid Host
  header"** ([#3611083](https://www.drupal.org/project/mcp_tools/issues/3611083),
  based on julien's patch). mcp/sdk >= 0.3 enables DNS-rebinding protection
  with a localhost-only allowlist. Hostnames extracted from the configured
  `allowed_origins` are now passed to the middleware. Add your public
  hostname or origin to the Origin allowlist in the remote settings.
- **Audit logger TypeError on list values**
  ([#3612659](https://www.drupal.org/project/mcp_tools/issues/3612659)).
  Integer array keys crashed `sanitizeDetails()` under strict types.

### Added

- **`entity_reference_revisions` field support with Layout Paragraphs
  awareness** ([#3611085](https://www.drupal.org/project/mcp_tools/issues/3611085),
  patch by julien). Content tools can create paragraphs inline (items with a
  `type` key), preserve `behavior_settings`, and resolve `parent_ref`
  aliases between sibling paragraphs to real UUIDs. Plain
  `['target_id' => ...]` references keep working unchanged.

## [1.0.0-beta14] - 2026-06-12

### Fixed

- **PHP 8.4 `date()` TypeError on database-sourced timestamps.** Timestamps
  read from database queries arrive as strings, which PHP 8.4's `date()`
  rejects, breaking tools across the analysis, watchdog, cron, user and other
  services (47 call sites in 17 files). The timestamp argument is now cast to
  int. Affects all sites on PHP 8.4 / Drupal 12.

## [1.0.0-beta13] - 2026-06-12

### Added

- **Drupal AI ecosystem integration** (`mcp_tools_ai`, optional): exposes
  MCP Tools' Tool API tools to [drupal/ai](https://www.drupal.org/project/ai)
  and [AI Agents](https://www.drupal.org/project/ai_agents) as native Function
  Calls, so in-site AI agents can use the same library MCP clients use. Curated
  by operation (read-only by default; write/trigger opt-in) and configurable at
  `/admin/config/services/mcp-tools/ai`.

### Note

- Includes everything from the unreleased beta8 to beta12 line: the `/_mcp_tools`
  route-level access check (#3587523), mcp/sdk 0.3 to 0.6 support (#3589053),
  Drupal 12 compatibility, and a fully restored CI/release pipeline.

## [1.0.0-beta4] - 2026-02-25

### Fixed

- **ApiKeyManagerTest failures on Drupal 11**: The beta3 frozen REQUEST_TIME fix changed `DrupalClock::now()` to use `getCurrentTime()`, but the test only mocked `getRequestTime()`, causing 4 failures on Drupal 11
- **CI dependency resolution**: Consolidated `composer require` steps with `-W` flag to resolve transitive conflicts between `mcp/sdk` and Drupal core packages

## [1.0.0-beta3] - 2026-02-25

### Fixed

- **Frozen timestamps in server mode**: `getRequestTime()` returns the process start time in long-running `drush mcp-tools:serve` sessions, causing all entities to share the same timestamp. Replaced with `getCurrentTime()` across 7 services: ContentService, MediaService, BatchService, TaxonomyManagementService, ModerationService, SchedulerService, and DrupalClock ([#3575317](https://www.drupal.org/project/issues/mcp_tools/3575317), reported by [guillaumeg](https://www.drupal.org/u/guillaumeg))

## [1.0.0-beta2] - 2026-02-06

### Changed

- Bump dependencies: `mcp-error-codes ^1.2`, `mcp-schema-builder ^1.1`, `mcp-tool-gateway ^1.1`
- Refactor DefaultToolErrorHandler to use McpError fluent builder
- Migrate all module services from hardcoded error strings to `ErrorCode::*` constants
- Fix list schema rejecting complex objects in batch tools

### Removed

- Remove dev-only `DRUPAL_ORG_DESCRIPTION.html` from public release

## [1.0.0-beta1] - 2026-01-09

### Added

- **DrupalToolProvider**: New adapter implementing `ToolProviderInterface` from mcp-tool-gateway for standardized tool discovery and execution patterns
- **Full MCP PHP ecosystem integration**: Now leverages 5 standalone Composer packages:
  - `code-wheel/mcp-error-codes: ^1.2` - ErrorCode constants + McpError fluent builder
  - `code-wheel/mcp-schema-builder: ^1.1` - TypeMapper, SchemaValidator, McpSchema presets
  - `code-wheel/mcp-tool-gateway: ^1.1` - ToolProviderInterface, middleware pipeline
  - `code-wheel/mcp-http-security: ^1.0` - API key validation, IP allowlist, scopes
  - `code-wheel/mcp-events: ^2.0` - Tool execution events

### Changed

- **DefaultToolErrorHandler**: Refactored to use `McpError` fluent builder for cleaner, more maintainable error responses
- **ErrorCode standardization**: All 24+ service files now use `ErrorCode::*` constants instead of hardcoded strings
- **ToolApiGateway**: Now uses DrupalToolProvider internally for consistent tool discovery

### Developer Experience

- **223 tools** across 35 submodules (up from 154)
- **741 unit tests** passing on Drupal 11 + PHP 8.4
- **Full CI pipeline** with Drupal 10.3 and 11.0 matrix testing

## [1.0.0-alpha26] - 2026-01-09

### Added

- **New standalone packages** for the PHP MCP ecosystem:
  - [code-wheel/mcp-error-codes](https://github.com/code-wheel/mcp-error-codes) v1.1 - Standardized error codes with helper methods
  - [code-wheel/mcp-events](https://github.com/code-wheel/mcp-events) v2.1 - Tool execution events with JsonSerializable support
- **Service interfaces**: Added `AccessManagerInterface`, `AuditLoggerInterface`, `RateLimiterInterface` for better testability
- **MenuManagementService**: Renamed from MenuService with proper interface
- **TaxonomyManagementService**: Renamed from TaxonomyService (in mcp_tools_structure) with proper interface

### Changed

- **External package dependencies**: Now uses external Composer packages instead of bundled code:
  - `code-wheel/mcp-error-codes: ^1.1` - Error code constants
  - `code-wheel/mcp-events: ^2.0` - Tool execution events (decoupled from mcp/sdk)
- **Service architecture**: Core services now implement interfaces for dependency injection
- **TemplateService**: Refactored for cleaner architecture with ComponentFactory
- **WriteAccessTrait**: Improved dry-run handling and confirmation flow

### Removed

- **Bundled event classes**: Removed `src/Mcp/Event/Tool*Event.php` (now in external package)
- **Duplicate services**: Removed duplicate TaxonomyService from mcp_tools_structure (uses core TaxonomyService)

### Fixed

- **McpToolsRemoteController**: Improved error handling and response formatting
- **SchedulerService**: Fixed service injection and method signatures

## [1.0.0-alpha25] - 2026-01-08

### Added

- **`mcp_tools_jsonapi` submodule**: Generic entity CRUD via JSON:API (6 new tools)
  - `mcp_jsonapi_discover_types` - Discover all available entity types/bundles
  - `mcp_jsonapi_get_entity` - Retrieve entity by UUID
  - `mcp_jsonapi_list_entities` - List entities with filtering and pagination
  - `mcp_jsonapi_create_entity` - Create any entity type
  - `mcp_jsonapi_update_entity` - Update entity by UUID
  - `mcp_jsonapi_delete_entity` - Delete entity by UUID
  - Configurable allowlist/blocklist for entity types
  - Security: user, shortcut, shortcut_set always blocked
  - Settings form at `/admin/config/services/mcp-tools/jsonapi`
- **`mcp_search_api_search` tool**: Search content via Search API indexes with keywords, filters, and pagination (added to `mcp_tools_search_api`)
- **`mcp:dev-profile` command**: One-command setup for development - applies development preset and enables recommended submodules
- **Grouped submodule display**: Status page and `mcp:status` now show all 35 submodules grouped by category (core-only, contrib-dependent, infrastructure)

### Security

- **UserService**: Added admin role protection to `blockUser()` - users with administrator role cannot be blocked via MCP
- **UserService**: Added input validation (username max 60 chars, email max 254 chars, email format validation)
- **RoleService**: Expanded `DANGEROUS_PERMISSIONS` from 10 to 19 entries including `administer blocks`, `administer views`, `create url aliases`
- **MenuService**: Enhanced URI validation to decode URL-encoded characters and prevent bypass attempts (e.g., `%6Aavascript:`)

### Fixed

- **ContentTypeService**: Fixed critical infinite recursion bug in `getEntityFieldManager()` that called itself instead of the service container
- **TaxonomyService**: Fixed N+1 query issues with new `getTermCountsByVocabulary()` and `batchLoadParents()` methods
- **mcp:status**: Tool counts now queried dynamically instead of hardcoded

### Changed

- **Bloat cleanup**: Removed Policy engine, Component system, telemetry module, workflows module, and example module
- **Config cleanup**: Removed `policy` section from `mcp_tools.settings.yml` and schema
- **Documentation**: Removed WordPress references, updated tool counts, removed COMPONENTS.md
- **Lifecycle consistency**: Added `lifecycle: experimental` to 9 submodules missing it
- **Package standardization**: Standardized `package: MCP Tools` across all submodules
- **Test updates**: Updated ToolSchemaKernelTest tool count (156 → 154), removed deleted modules from test lists

## [1.0.0-alpha24] - 2026-01-08

### Changed

- **Execution user configuration** for remote HTTP endpoint:
  - Added "Use site admin (uid 1)" checkbox for simple development setup
  - Added "Create MCP Executor Account" button to create dedicated service user/role
  - Removed automatic user creation on module install (now user-initiated)
  - Removed runtime block on uid 1 execution (now allowed via checkbox)
- Updated documentation to reflect execution user options

### Fixed

- Release workflow now installs `mcp-http-security` package before tests

## [1.0.0-alpha23] - 2026-01-08

### Fixed

- **Restored 4 unit tests** that were incorrectly marked as testing debt:
  - `RedirectServiceTest` - fixed assertion to match actual API (success=true, found=false)
  - `ContentTypeServiceTest` - fixed with proper DI for EntityFieldManagerInterface
  - `FieldServiceTest` - updated for new addField() signature and getFieldTypes() format
  - `WebformServiceTest` - fixed method names (listSubmissions→getSubmissions), removed dead tests
- `ContentTypeService` now uses proper dependency injection instead of static `\Drupal::service()` calls

## [1.0.0-alpha22] - 2026-01-07

### Added

- **New standalone package**: [code-wheel/mcp-http-security](https://github.com/code-wheel/mcp-http-security) extracted for the PHP MCP ecosystem
  - API key management with secure hashing (SHA-256 + pepper)
  - IP allowlisting with CIDR support (IPv4/IPv6)
  - Origin/hostname allowlisting with wildcard subdomains
  - PSR-15 SecurityMiddleware for any PHP framework
  - Multiple storage backends: Array, File, PDO
- **Drupal adapters** for the extracted package:
  - `DrupalStateStorage` - bridges Drupal State API to StorageInterface
  - `DrupalClock` - bridges Drupal TimeInterface to PSR-20 ClockInterface

### Changed

- `mcp_tools_remote` now delegates to `code-wheel/mcp-http-security` package
- Backward compatible: existing API keys continue to work unchanged
- Cleaner architecture with proper separation of concerns

### Dependencies

- Added `code-wheel/mcp-http-security: ^1.0` requirement

## [1.0.0-alpha21] - 2026-01-07

### Added

- **100% service test coverage**: All 48 services now have unit or kernel tests
  - New kernel tests for contrib-dependent services: EntityClone, Metatag, Pathauto, Scheduler, SearchApi, Sitemap, UltimateCron
  - Extended StructureServicesKernelTest with TaxonomyService coverage
  - Added kernel tests for Redirect, Webform services
  - ToolSchemaKernelTest expanded with MCP contract validations
- **Admin UI improvements**:
  - Permissions tab showing tool access by scope/category
  - Remote settings tab for HTTP endpoint configuration
- **Docker development environment**: `docker-compose.yml` + setup scripts for contributors
- **Strategic roadmap**: Community-driven roadmap items for post-adoption phase

### Fixed

- `date()` type errors in AnalyzePerformance and other analysis tools
- Unit tests no longer require contrib module dependencies

### Changed

- Clarified contrib module extraction plans (separate module, not submodule)

## [1.0.0-alpha20] - 2026-01-04

### Added

- **Configuration mode presets**: Development, Staging, Production modes with sensible defaults
  - Development: Full access, no rate limiting
  - Staging: Config-only mode, rate limited, audit logging
  - Production: Read-only mode, strict limits, full audit
- **Compound operations** in `mcp_tools_structure`:
  - `mcp_structure_scaffold_content_type` - Create content type + fields in one call
  - `mcp_structure_setup_taxonomy` - Create vocabulary + terms with hierarchy
- **Text format tools** in base module:
  - `mcp_list_text_formats` - List available text formats
  - `mcp_get_text_format` - Get format details including allowed HTML
- **Architecture documentation**: New `docs/ARCHITECTURE.md` with design patterns, security model

### Changed

- **idempotentHint** now set for read operations (TRUE for safe-to-retry ops)
- ROADMAP.md updated with completed P1 items

## [1.0.0-alpha19] - 2026-01-04

### Added

- **6 new schema discovery tools** in `mcp_tools_structure` for AI introspection:
  - `mcp_structure_list_content_types` - List all content types with field counts
  - `mcp_structure_get_content_type` - Get full field schema with types, cardinality, and allowed values
  - `mcp_structure_list_vocabularies` - List all vocabularies with term counts
  - `mcp_structure_get_vocabulary` - Get vocabulary details with terms and hierarchy
  - `mcp_structure_list_roles` - List all roles with permission/user counts
  - `mcp_structure_get_role_permissions` - Get permissions grouped by provider module
- **`destructive: TRUE` flag** added to 6 more tools: DisableTheme, DisableView, DisableJob, DisableLayoutBuilder, CancelSchedule, RevokePermissions (38 total destructive tools)
- **Remediation hints** added to 32 error messages across 12 services, guiding Claude to the correct discovery tools

### Changed

- **181 tool files updated** with rich, meaningful descriptions for all inputs and outputs
- All empty `TranslatableMarkup('')` entries replaced with helpful descriptions explaining:
  - Expected formats and valid values
  - Field type documentation (entity_reference, text_with_summary, etc.)
  - When to use each tool and relationships between tools
- ROADMAP.md cleaned up and simplified, removing completed phases

### Fixed

- Error messages now consistently reference discovery tools (e.g., "Content type 'blog' not found. Use mcp_structure_list_content_types to see available types.")

## [1.0.0-alpha18] - 2026-01-04

### Added

- Expanded unit test coverage for analysis/config/templates/batch/migration services to improve reliability and raise overall coverage.

### Changed

- Batch and migration operations now create entities via injected storages (instead of static `::create()` calls) to improve testability and consistency.

### Fixed

- Template application now logs audit events with the correct parameters (avoids runtime errors when audit logging is enabled).

## [1.0.0-alpha17] - 2026-01-04

### Added

- Optional Origin/Host allowlist for the remote HTTP endpoint (`mcp_tools_remote.settings.allowed_origins`) as defense-in-depth against DNS rebinding.
- JS MCP SDK STDIO smoke test (`scripts/mcp_js_sdk_compat.mjs`, in the development repository) wired into CI to catch strict-client schema regressions early.
- HTTP transport E2E coverage for Origin/Host allowlist behavior.

## [1.0.0-alpha16] - 2026-01-04

### Added

- Regression coverage for tools with no inputs to ensure MCP JSON Schemas encode empty `properties` as `{}`.

### Changed

- STDIO usage docs/examples now include `--uid` and recommend a dedicated execution user for shared environments.

### Fixed

- Tools with no inputs now return an MCP `inputSchema.properties` value that encodes as an object (`{}`), improving compatibility with strict MCP clients (Codex/Claude Code).

## [1.0.0-alpha15] - 2026-01-04

### Added

- DrupalCI (`.gitlab-ci.yml`) configuration to test across a broader core/PHP matrix.
- `drush mcp-tools:remote-setup` to create a dedicated remote execution user/role and configure `mcp_tools_remote.settings.uid`.
- Expanded config preview support: role create/delete, grant/revoke permissions, delete content type, and delete field preview operations.
- `DRUPALCI.md` and local testing notes to make CI failures easier to triage.

### Changed

- Audit logs now include a per-tool-call correlation ID, transport, client identifier, and active scopes.
- Status page now shows the remote HTTP endpoint (when enabled) and warns on risky remote settings (uid 1, empty allowlist, include-all-tools).
- CI "full tool registration" now validates schema conversion for all tools (not just registration count).

### Fixed

- STDIO E2E sets a known baseline for read-only/config-only so local reruns are deterministic.

## [1.0.0-alpha14] - 2026-01-04

### Changed

- Remote HTTP transport now refuses to execute as uid 1 (runtime enforcement, not just UI validation).
- HTTP transport E2E now validates IP allowlist enforcement and runs as a dedicated service user with only the required `mcp_tools use …` permissions.
- Drupal.org description and README now highlight read-only default scopes and include recommended starter bundles.

## [1.0.0-alpha13] - 2026-01-04

### Added

- Remote HTTP hardening: optional IP allowlist (`mcp_tools_remote.settings.allowed_ips`) and API key TTL support (`drush mcp-tools:remote-key-create --ttl=...`).
- Kernel coverage to ensure all core tool definitions convert cleanly to MCP tool annotations + JSON schemas.
- HTTP transport E2E now validates config-only mode (config writes allowed, ops writes denied).

### Changed

- Default connection scopes are now read-only by default (`access.default_scopes: [read]`).
- Admin-scope tools are now declared as `ToolOperation::Trigger` so they can be gated by admin scope at the Tool API access layer (recipes/templates/config export).
- Rate limiting now ignores the client-provided `X-MCP-Client-Id` header by default; opt-in via `rate_limiting.trust_client_id_header`.

## [1.0.0-alpha12] - 2026-01-03

### Added

- MCP-scoped config-change tracking via a tool-call context and a `config.save/delete/rename` subscriber (enables `mcp_config_mcp_changes` to reflect real tool activity).
- Unit/kernel coverage for previously untested core services: Analysis (broken links), Recipes, and Structure (content types + fields).
- `codecov.yml` to enforce high patch coverage while overall coverage ramps up.

### Changed

- `mcp_analysis_broken_links` adds an optional `base_url` input for STDIO/CLI usage.
- Broken-link scanner now enforces the `allowed_hosts` allowlist and blocks redirects to non-allowlisted hosts.
- CI no longer runs Drupal 11 on PHP 8.3 (Composer now requires PHP 8.4+ for current Drupal 11 releases).

## [1.0.0-alpha11] - 2026-01-03

### Added

- Additional unit coverage for Tool API access gating, cron/cache/image styles, and moderation services.

### Changed

- Refactored multiple services to use dependency injection (improves testability and reduces static `\Drupal::*` usage).
- Entity creation in write services now uses storage `->create()` instead of static entity `::create()` helpers.

### Fixed

- Cron job discovery now uses `ModuleHandlerInterface::invokeAllWith()` (prevents calling a non-existent `getImplementations()` method).
- `mcp_tools_recipes` now registers `logger.channel.mcp_tools_recipes` so the service container compiles cleanly in kernel tests.

## [1.0.0-alpha10] - 2026-01-03

### Added

- Tool metadata lint test to prevent read/write operation regressions.
- Kernel coverage for config-only mode gating by write kind.

### Fixed

- `mcp_upload_file` now correctly declares a write operation (requires write scope).
- Status page now shows config-only mode status.

## [1.0.0-alpha9] - 2026-01-03

### Added

- Config-only mode (restrict write tools to configuration changes) with configurable allowed write kinds.

### Fixed

- Corrected multiple Tool API operation declarations so write tools are not incorrectly exposed as read operations.

### Security

- Enforced correct write-scope gating for tools that mutate configuration or operational state (e.g., block placement, pathauto alias generation, Search API indexing).

## [1.0.0-alpha8] - 2026-01-03

### Added

- Unit coverage for schema conversion, error formatting, config management previews/exports, and remote controller failure paths.
- Additional unit coverage for batch/migration helpers and watchdog message formatting.

### Changed

- Code coverage job now runs unit + kernel + functional suites and excludes contrib-dependent submodules (unless their dependencies are installed).
- Release workflow now runs submodule unit/kernel/functional tests (not just base module tests).

### Security

- Base64 uploads are capped and block dangerous executable extensions by default.
- Serialized watchdog/metatag parsing now enforces size limits to prevent memory exhaustion.

## [1.0.0-alpha7] - 2026-01-03

### Added

- End-to-end MCP transport checks (STDIO + HTTP) wired into CI and release workflows.

### Fixed

- `drush mcp-tools:serve` no longer writes human output to STDOUT (prevents corrupting the STDIO JSON-RPC stream).
- `mcp_tools_remote` HTTP transport now uses a persistent session store (prevents 404 "session not found" after `initialize`).
- Drush command registration moved to per-submodule `drush.services.yml` files (avoids runtime dependency on Drush and fixes command discovery).
- Tool execution output handling now tolerates incomplete Tool API output contexts (prevents MCP tool calls failing with "provided context ... is not valid").

## [1.0.0-alpha6] - 2026-01-03

### Added

- Unit coverage for `TemplateService` and `WebhookNotifier` (signature + redaction + SSRF blocking).

### Fixed

- Functional UI tests now grant `access administration pages` and assert anonymous access denial robustly across login/403 configurations.
- CI now installs Drupal dev dependencies with `--with-all-dependencies` to avoid Drupal 11 Composer lock conflicts (e.g. `sebastian/diff` vs PHPUnit).

## [1.0.0-alpha5] - 2026-01-03

### Added

- `mcp_tools_mcp_server` optional bridge submodule (depends on `drupal/mcp_server`) with a Drush sync command to generate MCP Server tool configs for MCP Tools.

## [1.0.0-alpha4] - 2026-01-03

### Added

- `mcp_tools_stdio` submodule to run an MCP server over STDIO via Drush (`drush mcp-tools:serve`).
- `mcp_tools_remote` submodule to expose an HTTP MCP endpoint at `/_mcp_tools` using API key authentication.
- MCP server bridge classes for exposing Tool API tools via the official `mcp/sdk`.
- Unit test to ensure every tool category has a corresponding permission, plus unit coverage for remote API key management.

### Fixed

- Added missing `mcp_tools use {category}` permissions so non-uid1 roles can be granted access to all tool categories.

## [1.0.0-alpha3] - 2026-01-03

### Added

- Kernel smoke coverage to instantiate all core tools (144) and ensure `access()` never throws.

### Changed

- CI tool registration check now enforces a minimum of 144 MCP Tools tools (core-only install).

### Fixed

- `SystemStatusService` no longer references non-existent `SystemManager::REQUIREMENT_INFO` (Drupal 10/11).

## [1.0.0-alpha2] - 2026-01-03

### Added

- Tool API plugin implementations for all MCP Tools (205 tools) using PHP attributes (`#[Tool(...)]`) and `Plugin/tool/Tool` discovery.
- `Drupal\\mcp_tools\\Tool\\McpToolsToolBase` wrapper to adapt legacy MCP Tools responses into Tool API `ExecutableResult` objects and enforce category permissions + MCP scopes.
- Access configuration hardening: `access.allowed_scopes` plus trust toggles for reading scopes from headers, query params, and environment variables.
- Webhook host allowlist via `webhooks.allowed_hosts`.
- Read-operation rate limiting helpers and expanded kernel coverage.
- Kernel coverage for Tool API discovery/registration.

### Changed

- Minimum requirements: Drupal `^10.3 || ^11`, PHP `>=8.3`.
- MCP Server is optional (only required when exposing tools over MCP).

### Fixed

- Drupal 11 / Symfony 7 test compatibility and Tool API listing compatibility.

### Security

- Scope override values are intersected with `access.allowed_scopes` to prevent accidental privilege escalation via scope injection.
- Configuration analysis and audit logging redact sensitive values by default.

## [1.0.0-alpha1] - 2025-01-02

### Added

#### Core Module (mcp_tools) - 23 read-only tools
- **Site Health:** GetSiteStatus, GetSystemStatus, CheckSecurityUpdates, CheckCronStatus, AnalyzeWatchdog, GetQueueStatus, GetFileSystemStatus
- **Content:** ListContentTypes, GetRecentContent, SearchContent, GetVocabularies, GetTerms, GetFiles, FindOrphanedFiles
- **Configuration:** GetConfigStatus, GetConfig, ListConfig
- **Users:** GetRoles, GetUsers, GetPermissions
- **Structure:** GetMenus, GetMenuTree

#### Core Services
- **AccessManager** - Three-layer access control (module-based, global read-only mode, connection scopes)
- **RateLimiter** - Per-client, per-operation-type rate limiting with configurable limits
- **AuditLogger** - Operation logging with sensitive data redaction
- **WebhookNotifier** - HMAC-signed webhook notifications for external systems

#### Admin UI
- Settings form at `/admin/config/services/mcp-tools`
- Status page at `/admin/config/services/mcp-tools/status`
- Configurable access control, rate limiting, and webhook settings

#### Write/Analysis Submodules - 182 tools across 29 submodules

| Submodule | Tools | Description |
|-----------|-------|-------------|
| **mcp_tools_content** | 4 | Content CRUD (create, update, delete, publish) |
| **mcp_tools_structure** | 12 | Content types, fields, taxonomy, roles, permissions |
| **mcp_tools_users** | 5 | User management (create, update, block, roles) |
| **mcp_tools_menus** | 5 | Menu management (menus and links) |
| **mcp_tools_views** | 6 | Views creation and management |
| **mcp_tools_blocks** | 5 | Block placement and configuration |
| **mcp_tools_media** | 6 | Media types, uploads, entities |
| **mcp_tools_webform** | 7 | Webform creation and submissions |
| **mcp_tools_theme** | 8 | Theme settings and management |
| **mcp_tools_layout_builder** | 9 | Layout Builder sections and blocks |
| **mcp_tools_recipes** | 6 | Drupal Recipes (10.3+) |
| **mcp_tools_config** | 5 | Configuration export and tracking |
| **mcp_tools_paragraphs** | 6 | Paragraphs type and field management |
| **mcp_tools_moderation** | 6 | Content Moderation workflows and states |
| **mcp_tools_scheduler** | 5 | Scheduled publish/unpublish (contrib) |
| **mcp_tools_metatag** | 5 | SEO meta tags management (contrib) |
| **mcp_tools_image_styles** | 7 | Image styles and effects |
| **mcp_tools_cache** | 6 | Cache management and invalidation |
| **mcp_tools_cron** | 5 | Cron execution and queue processing |
| **mcp_tools_ultimate_cron** | 6 | Ultimate Cron job management (contrib) |
| **mcp_tools_pathauto** | 6 | URL alias patterns (contrib) |
| **mcp_tools_redirect** | 7 | URL redirects (contrib) |
| **mcp_tools_sitemap** | 7 | XML sitemap management (contrib) |
| **mcp_tools_search_api** | 8 | Search API index management (contrib) |
| **mcp_tools_entity_clone** | 4 | Entity cloning (contrib) |
| **mcp_tools_analysis** | 8 | Site analysis (SEO, a11y, security, performance) |
| **mcp_tools_batch** | 6 | Bulk operations (create, update, delete, publish) |
| **mcp_tools_templates** | 5 | Site templates (blog, portfolio, business, docs) |
| **mcp_tools_migration** | 7 | Content import/export (CSV, JSON) |

### Security

- **Three-layer access control** - Modules, global toggle, connection scopes
- **Rate limiting** - Configurable per-minute/hour limits by operation type (now includes admin operations)
- **Audit logging** - All operations logged with user info
- **SSRF protection** - Webhook URLs validated against private IPs and cloud metadata services
- **Role escalation protection** - Pattern-based blocking of admin/super roles
- **Import field protection** - Protected fields (uid, nid, moderation_state, etc.) cannot be set via import
- **Menu URI validation** - Strict scheme whitelist prevents javascript:/data: XSS attacks
- **Secure session identification** - Rate limiter uses system-level identifiers resistant to spoofing
- **Sensitive data redaction** - Passwords and secrets never logged or sent
- **Protected entities** - uid 1, administrator role, system menus, core views
- **Dangerous permissions blocked** - Cannot grant admin permissions via MCP
- **Webhook signatures** - HMAC-SHA256 signed webhook payloads

### Testing

- Unit tests for AccessManager, RateLimiter, AuditLogger
- Kernel tests for access control and rate limiting integration
- Security-focused tests for bypass prevention

---

## Tool Count Summary

| Category | Tools |
|----------|-------|
| Base module (read-only) | 22 |
| mcp_tools_content | 4 |
| mcp_tools_structure | 12 |
| mcp_tools_users | 5 |
| mcp_tools_menus | 5 |
| mcp_tools_views | 6 |
| mcp_tools_blocks | 5 |
| mcp_tools_media | 6 |
| mcp_tools_webform | 7 |
| mcp_tools_theme | 8 |
| mcp_tools_layout_builder | 9 |
| mcp_tools_recipes | 6 |
| mcp_tools_config | 5 |
| mcp_tools_paragraphs | 6 |
| mcp_tools_moderation | 6 |
| mcp_tools_scheduler | 5 |
| mcp_tools_metatag | 5 |
| mcp_tools_image_styles | 7 |
| mcp_tools_cache | 6 |
| mcp_tools_cron | 5 |
| mcp_tools_ultimate_cron | 6 |
| mcp_tools_pathauto | 6 |
| mcp_tools_redirect | 7 |
| mcp_tools_sitemap | 7 |
| mcp_tools_search_api | 8 |
| mcp_tools_entity_clone | 4 |
| mcp_tools_analysis | 8 |
| mcp_tools_batch | 6 |
| mcp_tools_templates | 5 |
| mcp_tools_migration | 7 |
| **Total** | **205** |
