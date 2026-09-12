# Shared-Helper Extraction Migration Plan

Scope: extracting `sanitize_key_field()` and the library option key out of
`AcrossAI_Ability_Library_Config` before that class is deleted in Feature 102 Phase 8.

## Current State

```text
Modules/Library/AcrossAI_Ability_Library_Config   ← scheduled for DELETION (T073)
   ├── sanitize_key_field()   ──┬─> AcrossAI_Ability_Library_Registry        (×5)  RETAINED
   │                            ├─> Integrations/AcrossAI_Integration_Ability_Base (×1)  RETAINED
   │                            └─> Rest/AcrossAI_Ability_Library_Config_Controller (×1) deleted
   ├── OPTION_KEY             ──┬─> AcrossAI_Category_Slug_Migration          (×2)  RETAINED
   │                            └─> AcrossAI_Library_Gate_Migration (new)           RETAINED
   └── get_config()           ──┬─> Ability_Definition:225,247  (removed by T043)
                                └─> AcrossAI_Ability_Library_Processor:70 (removed by T042)
```

### Problems

- A helper with **six consumers** lives on a class being deleted — Constitution §VI requires common logic
  to move to `includes/Utilities/` before its *second* use, let alone its sixth.
- Deleting the class as T073 specifies fatals three retained classes on every request.
- The new gate migration would take a dependency on a module scheduled for removal, and on multisite it
  runs lazily — potentially after that removal ships (SEC-011).

## Target State

```text
includes/Utilities/AcrossAI_Key_Sanitizer         ← new, context-neutral
   └── sanitize_key_field()  <─ Registry, Integration base, (any future consumer)

AcrossAI_Category_Slug_Migration::SOURCE_OPTION   ← own constant
AcrossAI_Library_Gate_Migration::SOURCE_OPTION    ← own constant, reads get_site_option() directly

Modules/Library/AcrossAI_Ability_Library_Config   ← deletable with zero remaining references
```

### Benefits

- T073 becomes a safe deletion instead of a site-wide fatal.
- The gate migration depends on nothing scheduled for removal, so lazily-migrating network sites keep a
  working path indefinitely.
- Satisfies Constitution §VI rather than deferring it again.

## Migration Phases

### Phase 1: Extract the helper (≈0.5 day)

**Goal**: `sanitize_key_field()` exists in `includes/Utilities/` and both retained consumers use it.

- **Task 1.1**: Create `includes/Utilities/AcrossAI_Key_Sanitizer.php`, namespace
  `AcrossAI_Abilities_Manager\Includes\Utilities`, carrying the method body verbatim.
- **Task 1.2**: Repoint `AcrossAI_Ability_Library_Registry.php` lines 417, 418, 462, 474, 498.
- **Task 1.3**: Repoint `Integrations/AcrossAI_Integration_Ability_Base.php` line 320 and the docblock
  reference at line 166.
- **Task 1.4**: Move the existing unit coverage for the helper onto the new class.

**Coexistence**: `AcrossAI_Ability_Library_Config::sanitize_key_field()` stays as a thin delegate to the
utility for the duration of the feature. Nothing breaks mid-flight, and the delegate disappears with the
class in Phase 3.

### Phase 2: Own the option key (≈0.25 day)

**Goal**: no retained class reads `AcrossAI_Ability_Library_Config::OPTION_KEY`.

- **Task 2.1**: Add `SOURCE_OPTION = 'acrossai_library_config'` to `AcrossAI_Category_Slug_Migration` and
  repoint lines 132, 155.
- **Task 2.2**: Add the same constant to `AcrossAI_Library_Gate_Migration` and read `get_site_option()`
  directly — the migration must reference no symbol scheduled for deletion.
- **Task 2.3**: Assert in a test that `AcrossAI_Library_Gate_Migration` has zero references to
  `AcrossAI_Ability_Library_Config`.

**Coexistence**: two constants briefly hold the same literal. That duplication is deliberate and
short-lived — a shared constant would recreate the dependency this phase exists to remove.

### Phase 3: Delete (part of Feature 102 Phase 8)

**Goal**: `AcrossAI_Ability_Library_Config` deleted with zero references.

- **Task 3.1**: `grep -rEn 'AcrossAI_Ability_Library_Config'` over `includes/ src/ tests/ admin/` returns
  only the class file itself.
- **Task 3.2**: Delete the class and the delegate from Phase 1.

**Coexistence**: none required — this is the terminal step.

## Coexistence Strategy

**Why coexistence?** The deletion and the extraction ship in the same feature, so the two must not race.
Keeping a delegate on the old class means every phase boundary leaves a working plugin, and the extraction
can land and be reviewed on its own merits well before anything is removed.

**How**:
- New and repointed code calls the utility immediately.
- The old static remains as a one-line delegate until the class is deleted.
- The delegate is the *only* thing left referencing the helper on the old class, so the final grep is
  unambiguous.

## Rollback Plan

Each phase is independently revertable. Phase 1 and 2 are additive — reverting restores direct calls with
no data implications. Phase 3 is the only destructive step and is gated on Task 3.1 returning clean; if it
does not, stop and do not delete.

## Success Criteria

- [ ] `sanitize_key_field()` lives in `includes/Utilities/` with all six consumers repointed
- [ ] No retained class references `AcrossAI_Ability_Library_Config`
- [ ] `AcrossAI_Library_Gate_Migration` reads its source option through its own constant
- [ ] `composer phpstan` and `composer phpcs` clean; PHPUnit green
- [ ] The Phase 3 grep returns only the class file before deletion
