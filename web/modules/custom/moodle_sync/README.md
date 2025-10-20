# Moodle Sync

This module syncs drupal entities from Drupal to Moodle via built-in webservices.

There are four separate submodules that handle the sync of Moodle components. Enable all needed modules and configure them appropriately:

- moodle_sync_category: syncs Moodle course categories
- moodle_sync_course: syncs Moodle courses
- moodle_sync_enrollments: syncs Moodle user enrollments
- moodle_sync_users: syncs Moodle users

## Common concepts

- On **Creation** of a Drupal entity, the corresponding Moodle component is created
  - The Drupal *entity ID* is written into Moodle component *idnumber*
  - *Moodle ID*  is written back into the Drupal entity's *field_moodle_id*

- On **Updating** a Drupal entity, the corresponding Moodle component is looked up via its id from *field_moodle_id* and updated with all the fields mapped for this component in the module's configuration.
  - If no corresponding Moodle component to update is found, nothing will be synced to Moodle.
  - If *field_moodle_id* is empty when updating a Drupal entity, this module will try to create a new component in Moodle

Thus, if you want to stop a particular entity from syncing to Moodle, you can enter an invalid Moodle ID into its *field_moodle_id*, eg. "ignore"

### Field mappings

- Fields to sync other than the Moodle IDNUMBER and Drupal *field_moodle_id* can be configured for courses and users these module's settings.
- Categories will always sync field_description to the Category description in Moodle.

## How to install

### Moodle Configuration
- Enable web services (/admin/search.php?query=enablewebservices)
- Enable rest protocol (/admin/settings.php?section=webserviceprotocols)
- Add a service (/admin/settings.php?section=externalservices)
  - Short name = moodle_sync
  - Enabled = true
  - Authorized users only = true
- For your service, click on authorized users (/admin/settings.php?section=externalservices)
- Add your user (can be the system admin, or any account with sufficient permissions)
- Create a token for this user and service (/admin/webservice/tokens.php)
- Copy that token to the Drupal module configuration
- Add functions to your webservice (/admin/settings.php?section=externalservices)
  - core_cohort_add_cohort_members                (only used for moodle_sync_cohorts)
  - core_cohort_create_cohorts                    (only used for moodle_sync_cohorts)
  - core_cohort_delete_cohorts                    (only used for moodle_sync_cohorts)
  - core_cohort_delete_cohort_members             (only used for moodle_sync_cohorts)
  - core_cohort_update_cohorts                    (only used for moodle_sync_cohorts)
  - core_course_create_courses
  - core_course_create_categories
  - core_course_delete_courses
  - core_course_duplicate_course
  - core_course_get_categories
  - core_course_get_courses_by_field
  - core_course_update_courses
  - core_course_update_categories
  - core_enrol_get_enrolled_users
  - core_role_unassign_roles
  - core_user_create_users
  - core_user_delete_users
  - core_user_get_users
  - core_user_get_users_by_field
  - core_user_update_users
  - enrol_manual_enrol_users
  - enrol_manual_unenrol_users

### Drupal Configuration
- Enable module and all submodules that will be used.
- Necessary settings can be found in /admin/config/moodle_sync/settings
- As a safety measure, the current Drupal path has to be given in the module settings. This makes sure that the module stops writing to Moodle if the current Drupal DB is copied to another site, eg for test sites.
