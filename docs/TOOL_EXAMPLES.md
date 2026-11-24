# Tool examples

Real calls with real arguments, grouped by submodule. Every call on this
page is made against a live test site before a release, through the
official MCP JS SDK client, and has to succeed. The names that start with
`sweep_` are the test data it creates and removes again. The ids (12, 5
and so on) are samples: in a real run they come from the call before.

This page is generated. Do not edit it by hand.

## Structure

- `mcp_structure_create_content_type` `{"id":"sweep_page","label":"Sweep page","description":"Made by the sweep.","create_body":true}`
- `mcp_structure_get_content_type` `{"id":"sweep_page"}`
- `mcp_structure_create_vocabulary` `{"id":"sweep_tags","label":"Sweep tags","description":"Made by the sweep."}`
- `mcp_structure_create_term` `{"vocabulary":"sweep_tags","name":"Alpha","description":"First."}`
- `mcp_structure_create_terms` `{"vocabulary":"sweep_tags","terms":["Beta","Gamma"]}`
- `mcp_structure_get_vocabulary` `{"id":"sweep_tags","limit":10}`
- `mcp_tools_get_terms` `{"vocabulary":"sweep_tags","limit":10,"hierarchical":true}`
- `mcp_structure_setup_taxonomy` `{"id":"sweep_topics","label":"Sweep topics","terms":["One","Two"]}`
- `mcp_structure_add_field` `{"bundle":"sweep_page","field_name":"sweep_note","field_type":"string","label":"Note","description":"A note."}`
- `mcp_structure_add_field` `{"bundle":"sweep_page","field_name":"sweep_tags","field_type":"entity_reference","label":"Tags","target_type":"taxonomy_term","target_bundles":["sweep_tags"],"cardinality":-1}`
- `mcp_structure_add_field` `{"bundle":"sweep_page","field_name":"sweep_kind","field_type":"list_string","label":"Kind","allowed_values":["a","b"]}`
- `mcp_structure_add_field` `{"bundle":"sweep_page","field_name":"sweep_level","field_type":"list_integer","label":"Level","allowed_values":[1,2]}`
- `mcp_structure_scaffold_content_type` `{"id":"sweep_event","label":"Sweep event","fields":[{"name":"place","type":"string","label":"Place"}],"include_body":true}`
- `mcp_structure_create_role` `{"id":"sweep_role","label":"Sweep role","permissions":["access content"]}`
- `mcp_structure_grant_permissions` `{"role":"sweep_role","permissions":["view own unpublished content"]}`
- `mcp_structure_get_role_permissions` `{"id":"sweep_role"}`
- `mcp_structure_revoke_permissions` `{"role":"sweep_role","permissions":["view own unpublished content"]}`

## Content

- `mcp_create_content` `{"type":"sweep_page","title":"Sweep one","fields":{"body":"Hello from the sweep.","field_sweep_note":"n"},"status":true}`
- `mcp_update_content` `{"nid":12,"updates":{"title":"Sweep one, changed"}}`
- `mcp_publish_content` `{"nid":12,"publish":false}`
- `mcp_publish_content` `{"nid":12,"publish":true}`
- `mcp_tools_search_content` `{"query":"Sweep","limit":5,"content_type":"sweep_page"}`
- `mcp_analysis_seo` `{"entity_type":"node","entity_id":12}`
- `mcp_analysis_accessibility` `{"entity_type":"node","entity_id":12}`
- `mcp_analysis_duplicates` `{"content_type":"sweep_page","field":"title","threshold":0.8}`
- `mcp_cache_clear_entity` `{"entity_type":"node","entity_id":"12"}`
- `mcp_cache_invalidate_tags` `{"tags":["node_list"]}`
- `mcp_cache_clear_bin` `{"bin":"render"}`
- `mcp_cache_rebuild` `{"type":"router"}`
- `mcp_cache_rebuild` `{"type":"theme"}`
- `mcp_cache_rebuild` `{"type":"menu"}`
- `mcp_cache_rebuild` `{"type":"container"}`

## Users

- `mcp_create_user` `{"username":"sweep_user","email":"sweep@example.com","roles":["sweep_role"],"status":true}`
- `mcp_update_user` `{"uid":5,"updates":{"email":"sweep2@example.com"}}`
- `mcp_assign_user_roles` `{"uid":5,"roles":["sweep_role"]}`
- `mcp_block_user` `{"uid":5}`
- `mcp_activate_user` `{"uid":5}`

## Batch

- `mcp_batch_create_content` `{"content_type":"sweep_page","items":[{"title":"Batch one"},{"title":"Batch two"}]}`
- `mcp_batch_publish` `{"ids":[13,14],"publish":false}`
- `mcp_batch_update_content` `{"updates":[{"id":13,"fields":{"title":"Batch 13"}},{"id":14,"fields":{"title":"Batch 14"}}]}`
- `mcp_batch_create_terms` `{"vocabulary":"sweep_tags","terms":["Delta","Epsilon"]}`
- `mcp_batch_assign_role` `{"role":"sweep_role","user_ids":[5]}`
- `mcp_batch_delete_content` `{"ids":[13,14],"force":true}`

## Menus

- `mcp_menus_create_menu` `{"id":"sweep-menu","label":"Sweep menu","description":"Made by the sweep."}`
- `mcp_menus_add_menu_link` `{"menu":"sweep-menu","title":"Home","uri":"internal:/","weight":1}`
- `mcp_menus_update_menu_link` `{"link_id":3,"updates":{"title":"Home, changed"}}`
- `mcp_tools_get_menu_tree` `{"menu":"sweep-menu","max_depth":2}`
- `mcp_menus_delete_menu_link` `{"link_id":3}`
- `mcp_menus_delete_menu` `{"id":"sweep-menu"}`

## Blocks

- `mcp_place_block` `{"plugin_id":"system_powered_by_block","region":"content","id":"sweep_powered","label":"Powered"}`
- `mcp_configure_block` `{"block_id":"sweep_powered","weight":5,"label":"Powered, changed"}`
- `mcp_remove_block` `{"block_id":"sweep_powered"}`

## Theme

- `mcp_theme_get_settings` `{"theme":"olivero"}`
- `mcp_theme_enable` `{"theme":"claro"}`
- `mcp_theme_set_admin` `{"theme":"claro"}`
- `mcp_theme_update_settings` `{"theme":"claro","settings":{"features":{"favicon":false}}}`
- `mcp_theme_set_default` `{"theme":"olivero"}`
- `mcp_theme_set_admin` `{"theme":"olivero"}`
- `mcp_theme_disable` `{"theme":"claro"}`

## Image styles

- `mcp_image_styles_create` `{"id":"sweep_style","label":"Sweep style"}`
- `mcp_image_styles_add_effect` `{"style_id":"sweep_style","effect_id":"image_scale","configuration":{"width":100,"height":100}}`
- `mcp_image_styles_get` `{"style_id":"sweep_style"}`
- `mcp_image_styles_remove_effect` `{"style_id":"sweep_style","effect_uuid":"<effect_uuid from the call before>"}`
- `mcp_image_styles_delete` `{"style_id":"sweep_style","force":true}`

## Media

- `mcp_create_media_type` `{"id":"sweep_image","label":"Sweep image","source_plugin":"image"}`
- `mcp_upload_file` `{"filename":"sweep.png","data":"<the file, base64 encoded>","directory":"public://sweep"}`
- `mcp_create_media` `{"bundle":"sweep_image","name":"Sweep image one","source_field_value":"7"}`
- `mcp_generate_alt_text` `{"mid":4,"overwrite":true}`
- `mcp_delete_media` `{"mid":4}`
- `mcp_delete_media_type` `{"id":"sweep_image"}`
- `mcp_create_media_type` `{"id":"sweep_video","label":"Sweep video","source_plugin":"video"}`
- `mcp_create_media_type` `{"id":"sweep_audio","label":"Sweep audio","source_plugin":"audio_file"}`
- `mcp_delete_media_type` `{"id":"sweep_video"}`
- `mcp_delete_media_type` `{"id":"sweep_audio"}`

## Layout Builder

- `mcp_layout_enable` `{"bundle":"sweep_page"}`
- `mcp_layout_get` `{"bundle":"sweep_page"}`
- `mcp_layout_allow_custom` `{"bundle":"sweep_page","allow":true}`
- `mcp_layout_add_section` `{"bundle":"sweep_page","layout_id":"layout_onecol","delta":0}`
- `mcp_layout_add_block` `{"bundle":"sweep_page","section_delta":0,"region":"content","block_id":"system_powered_by_block"}`
- `mcp_layout_remove_block` `{"bundle":"sweep_page","block_uuid":"<component_uuid from the call before>"}`
- `mcp_layout_remove_section` `{"bundle":"sweep_page","delta":0}`
- `mcp_layout_allow_custom` `{"bundle":"sweep_page","allow":false}`
- `mcp_layout_disable` `{"bundle":"sweep_page"}`

## Paragraphs

- `mcp_paragraphs_create_type` `{"id":"sweep_para","label":"Sweep paragraph","description":"Made by the sweep."}`
- `mcp_paragraphs_add_field` `{"bundle":"sweep_para","field_name":"sweep_text","field_type":"string","label":"Text"}`
- `mcp_paragraphs_get_type` `{"id":"sweep_para"}`
- `mcp_paragraphs_delete_field` `{"bundle":"sweep_para","field_name":"field_sweep_text"}`
- `mcp_paragraphs_delete_type` `{"id":"sweep_para","force":true}`

## Pathauto

- `mcp_pathauto_create` `{"id":"sweep_pattern","label":"Sweep pattern","pattern":"sweep/[node:title]","entity_type":"node","bundle":"sweep_page"}`
- `mcp_pathauto_get_pattern` `{"id":"sweep_pattern"}`
- `mcp_pathauto_update` `{"id":"sweep_pattern","label":"Sweep pattern, changed","weight":1}`
- `mcp_pathauto_get_pattern` `{"id":"sweep_pattern"}`
- `mcp_pathauto_update` `{"id":"sweep_pattern","bundle":"sweep_event"}`
- `mcp_pathauto_get_pattern` `{"id":"sweep_pattern"}`
- `mcp_pathauto_update` `{"id":"sweep_pattern","bundle":""}`
- `mcp_pathauto_get_pattern` `{"id":"sweep_pattern"}`
- `mcp_pathauto_generate` `{"entity_type":"node","bundle":"sweep_page","update":true}`
- `mcp_pathauto_delete` `{"id":"sweep_pattern"}`

## Redirect

- `mcp_redirect_create` `{"source":"sweep-old","destination":"/node/12","status_code":301}`
- `mcp_redirect_get` `{"id":2}`
- `mcp_redirect_find` `{"source":"sweep-old"}`
- `mcp_redirect_update` `{"id":2,"status_code":302}`
- `mcp_redirect_import` `{"redirects":[{"source":"sweep-old-two","destination":"/user"}]}`
- `mcp_redirect_delete` `{"id":2}`

## Metatag

- `mcp_metatag_get_entity` `{"entity_type":"node","entity_id":12}`
- `mcp_create_content` `{"type":"sweep_event","title":"Sweep with meta tags","status":true}`
- `mcp_metatag_set_entity` `{"entity_type":"node","entity_id":15,"tags":{"description":"Set by the sweep."}}`
- `mcp_metatag_get_entity` `{"entity_type":"node","entity_id":15}`

## Entity clone

- `mcp_entity_clone_settings` `{"entity_type":"node","bundle":"sweep_page"}`
- `mcp_entity_clone_clone` `{"entity_type":"node","entity_id":"12","title_prefix":"Clone of "}`
- `mcp_entity_clone_with_refs` `{"entity_type":"node","entity_id":"12","reference_fields":[]}`

## Scheduler

- `mcp_scheduler_get_schedule` `{"entity_id":12}`
- `mcp_create_content` `{"type":"sweep_event","title":"Sweep event one","status":false}`
- `mcp_scheduler_publish` `{"entity_id":17,"timestamp":"1893456000"}`
- `mcp_scheduler_unpublish` `{"entity_id":17,"timestamp":"1893459600"}`
- `mcp_scheduler_get_schedule` `{"entity_id":17}`
- `mcp_scheduler_cancel` `{"entity_id":17,"type":"all"}`

## Moderation

- `mcp_moderation_get_workflow` `{"id":"sweep_flow"}`
- `mcp_create_content` `{"type":"sweep_moderated","title":"Sweep moderated"}`
- `mcp_moderation_get_state` `{"entity_id":18}`
- `mcp_moderation_set_state` `{"entity_id":18,"state":"published","revision_message":"Published by the sweep."}`
- `mcp_moderation_get_history` `{"entity_id":18,"limit":5}`
- `mcp_moderation_get_content_by_state` `{"workflow_id":"sweep_flow","state":"published","limit":5}`

## Translation

- `mcp_get_translation_status` `{"nid":12}`
- `mcp_get_translatable_content` `{"nid":12}`
- `mcp_translate_content` `{"nid":12,"language":"fr","translations":{"title":"Bonjour","body":"Bonjour du sweep.","field_sweep_note":"n"}}`

## Migration

- `mcp_migration_field_mapping` `{"content_type":"sweep_page"}`
- `mcp_migration_validate` `{"content_type":"sweep_page","items":[{"title":"Import one"}]}`
- `mcp_migration_import_json` `{"content_type":"sweep_page","key":"id","items":[{"id":"one","title":"Import one"}]}`
- `mcp_migration_import_json` `{"content_type":"sweep_page","key":"id","items":[{"id":"one","title":"Import one"}]}`
- `mcp_migration_import_csv` `{"content_type":"sweep_page","key":"ref","csv_data":"ref,title\nr2,Import two\n"}`
- `mcp_migration_import_status` `{"import_id":"mcp_import_sweep_page__ref"}`
- `mcp_migration_rollback` `{"import_id":"mcp_import_sweep_page__ref"}`
- `mcp_migration_export_csv` `{"content_type":"sweep_page","limit":5}`
- `mcp_migration_export_json` `{"content_type":"sweep_page","limit":5}`
- `mcp_migration_export_json` `{"entity_type":"taxonomy_term","content_type":"sweep_tags","references":"names","limit":5}`
- `mcp_migration_export_csv` `{"entity_type":"taxonomy_term","content_type":"sweep_tags","references":"names","limit":5}`

## JSON:API

- `mcp_jsonapi_create_entity` `{"entity_type":"node","bundle":"sweep_page","fields":{"title":"From JSON:API"}}`
- `mcp_jsonapi_get_entity` `{"entity_type":"node","uuid":"<uuid from the call before>","bundle":"sweep_page"}`
- `mcp_jsonapi_list_entities` `{"entity_type":"node","bundle":"sweep_page","limit":5,"offset":0}`
- `mcp_jsonapi_update_entity` `{"entity_type":"node","uuid":"<uuid from the call before>","fields":{"title":"From JSON:API, changed"}}`
- `mcp_jsonapi_delete_entity` `{"entity_type":"node","uuid":"<uuid from the call before>"}`

## Views

- `mcp_create_view` `{"id":"sweep_view","label":"Sweep view","description":"Made by the sweep.","page_path":"sweep-view","items_per_page":5}`
- `mcp_add_view_display` `{"view_id":"sweep_view","display_type":"block","title":"Sweep block"}`
- `mcp_disable_view` `{"id":"sweep_view"}`
- `mcp_enable_view` `{"id":"sweep_view"}`
- `mcp_create_content_list_view` `{"id":"sweep_list","label":"Sweep list","content_type":"sweep_page","page_path":"sweep-list","block":true,"sort":"oldest","fields":["title","body"]}`
- `mcp_create_view` `{"id":"sweep_terms","label":"Sweep terms","base_table":"taxonomy_term_field_data","sort":"title","page_path":"sweep-terms"}`
- `mcp_create_view` `{"id":"sweep_users","label":"Sweep users","base_table":"users_field_data","block":true}`
- `mcp_create_content_list_view` `{"id":"sweep_untitled","label":"Sweep untitled","content_type":"sweep_page","show_title":false,"fields":["body"]}`
- `mcp_delete_view` `{"id":"sweep_untitled"}`
- `mcp_delete_view` `{"id":"sweep_view"}`
- `mcp_delete_view` `{"id":"sweep_list"}`
- `mcp_delete_view` `{"id":"sweep_terms"}`
- `mcp_delete_view` `{"id":"sweep_users"}`

## Webform

- `mcp_create_webform` `{"id":"sweep_form","title":"Sweep form","elements":{"name":{"#type":"textfield","#title":"Name"}}}`
- `mcp_get_webform` `{"id":"sweep_form"}`
- `mcp_update_webform` `{"id":"sweep_form","title":"Sweep form, changed","status":"closed"}`
- `mcp_get_webform_submissions` `{"webform_id":"sweep_form","limit":5,"offset":0}`
- `mcp_delete_webform` `{"id":"sweep_form"}`

## Sitemap

- `mcp_sitemap_entity_settings` `{"entity_type":"node","bundle":"sweep_page"}`
- `mcp_sitemap_set_entity` `{"entity_type":"node","bundle":"sweep_page","settings":{"index":true,"priority":"0.5"}}`
- `mcp_sitemap_update_settings` `{"variant":"default","settings":{"max_links":1000}}`
- `mcp_sitemap_regenerate` `{"variant":"default"}`

## Search API

- `mcp_search_api_get_server` `{"id":"sweep_server"}`
- `mcp_search_api_get_index` `{"id":"sweep_index"}`
- `mcp_search_api_status` `{"id":"sweep_index"}`
- `mcp_search_api_index` `{"id":"sweep_index","limit":50}`
- `mcp_search_api_search` `{"index":"sweep_index","keywords":"Sweep","limit":5}`
- `mcp_search_api_reindex` `{"id":"sweep_index"}`
- `mcp_search_api_clear` `{"id":"sweep_index"}`

## Cron

- `mcp_cron_update_settings` `{"threshold":3600}`
- `mcp_cron_run` (no arguments)
- `mcp_cron_reset_key` (no arguments)
- `mcp_ultimate_cron_get_job` `{"id":"system_cron"}`
- `mcp_ultimate_cron_disable` `{"id":"system_cron"}`
- `mcp_ultimate_cron_enable` `{"id":"system_cron"}`
- `mcp_ultimate_cron_run` `{"id":"system_cron"}`
- `mcp_ultimate_cron_logs` `{"id":"system_cron","limit":5}`

## Undo

- `mcp_create_content` `{"type":"sweep_page","title":"Sweep undo me"}`
- `mcp_undo_recent` `{"limit":5}`
- `mcp_undo_call` (no arguments)

## Config

- `mcp_tools_get_config` `{"name":"system.site"}`
- `mcp_get_text_format` `{"id":"plain_text"}`
- `mcp_config_diff` `{"config_name":"system.site"}`
- `mcp_config_preview` `{"operation":"create_role","params":{"id":"sweep_preview","label":"Sweep preview"}}`
- `mcp_config_export` `{"confirm":true}`

## Recipes and templates

- `mcp_recipes_create` `{"name":"sweep_recipe","description":"Made by the sweep.","label":"Sweep recipe","type":"Site","install":["node"]}`
- `mcp_recipes_get` `{"recipe_name":"sweep_recipe"}`
- `mcp_recipes_validate` `{"recipe_name":"sweep_recipe"}`
- `mcp_recipes_apply` `{"recipe_name":"sweep_recipe"}`
- `mcp_templates_get` `{"template_id":"<an id from mcp_templates_list>"}`
- `mcp_templates_preview` `{"template_id":"<an id from mcp_templates_list>"}`
- `mcp_templates_apply` `{"template_id":"<an id from mcp_templates_list>","skip_existing":true}`
- `mcp_templates_export` `{"name":"sweep_export","content_types":["sweep_page"],"vocabularies":["sweep_tags"],"roles":["sweep_role"]}`

## Clean up what the run made

- `mcp_delete_content` `{"nid":16,"confirm_delete_all":true}`
- `mcp_delete_content` `{"nid":17,"confirm_delete_all":true}`
- `mcp_delete_content` `{"nid":18,"confirm_delete_all":true}`
- `mcp_delete_content` `{"nid":15,"confirm_delete_all":true}`
- `mcp_delete_content` `{"nid":12,"confirm_delete_all":true}`
- `mcp_structure_delete_field` `{"bundle":"sweep_page","field_name":"field_sweep_note"}`
- `mcp_structure_delete_content_type` `{"id":"sweep_event","force":true}`
- `mcp_structure_delete_content_type` `{"id":"sweep_page","force":true}`
- `mcp_structure_delete_role` `{"id":"sweep_role"}`
- `mcp_cache_clear_all` (no arguments)
