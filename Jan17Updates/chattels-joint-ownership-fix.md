# Chattels Joint Ownership Fix

## Date: January 17, 2026

## Issue
The joint ownership dropdown in the Add Chattels form was not working correctly:
1. Dropdown was not showing the linked spouse account
2. No option to add a non-linked joint owner ("Other" option missing)
3. Ownership percentage was not editable (chattels don't require 50/50 split)

## Solution
Updated the ChattelFormModal to match the PropertyForm pattern for joint ownership handling.

## Files Changed

### Frontend

**resources/js/components/NetWorth/ChattelFormModal.vue**
- Changed `mapState` to direct getter access: `this.$store.getters['userProfile/spouse']`
- Added `jointOwnerSelection` data property to track dropdown state
- Added `joint_owner_name` to form data for non-linked joint owners
- Updated dropdown to show "(Spouse - Linked Account)" label
- Added "Other (Enter Name)" option for non-linked joint owners
- Added conditional text input when "Other" is selected
- Added visual ownership split display showing both shares
- Added editable ownership percentage input (defaults to 50%)
- Added `handleJointOwnerSelection()` method to manage linked vs. unlinked owners
- Updated `populateForm()` to restore selection state when editing
- Updated watch handler to clear joint owner data when switching to individual

**resources/js/components/NetWorth/ChattelsList.vue**
- Added `fetchFamilyMembers` dispatch in `mounted()` to ensure spouse data is available

### Backend

**database/migrations/2026_01_17_092200_add_joint_owner_name_to_chattels_table.php**
- Added `joint_owner_name` column to chattels table

**app/Models/Chattel.php**
- Added `joint_owner_name` to `$fillable` array

**app/Http/Requests/Chattel/StoreChattelRequest.php**
- Added `joint_owner_name` validation rule

**app/Http/Requests/Chattel/UpdateChattelRequest.php**
- Added `joint_owner_name` validation rule

### Data Fix

**resources/js/data/personas/widow.json**
- Changed `"status": "exempt"` to `"status": "within_7_years"` for gift records (database enum only allows `within_7_years` or `survived_7_years`)

## Key Pattern Used
The fix follows the established PropertyForm pattern for joint ownership:

```javascript
// Computed property (NOT mapState)
computed: {
  spouse() {
    return this.$store.getters['userProfile/spouse'];
  },
}

// Dropdown with linked account option
<select v-model="jointOwnerSelection" @change="handleJointOwnerSelection">
  <option value="">Select joint owner</option>
  <option v-if="spouse" :value="'linked_' + spouse.id">
    {{ spouse.name }} (Spouse - Linked Account)
  </option>
  <option value="other">Other (Enter Name)</option>
</select>

// Handler method
handleJointOwnerSelection() {
  if (this.jointOwnerSelection.startsWith('linked_')) {
    this.form.joint_owner_id = parseInt(this.jointOwnerSelection.replace('linked_', ''));
    this.form.joint_owner_name = '';
  } else if (this.jointOwnerSelection === 'other') {
    this.form.joint_owner_id = null;
  }
}
```

## Testing
1. Login as Chris Slater-Jones
2. Navigate to Chattels section
3. Click "Add Chattel"
4. Select "Joint" ownership type
5. Verify dropdown shows "Angela Slater-Jones (Spouse - Linked Account)"
6. Verify "Other (Enter Name)" option is available
7. Verify ownership percentage is editable and defaults to 50%
