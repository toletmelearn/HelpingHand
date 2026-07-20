# Teacher Substitution - Quick Reference Guide

**Quick understanding of how Teacher Substitution works**

---

## 🎯 What Is It?

A system to manage when a teacher is absent and needs someone to cover their class.

---

## 📊 Simple Flow

```
Teacher Absent → Admin Creates Record → System Suggests Substitute → Admin Approves → Done
```

---

## 🗂️ Main Components

### 1. **Substitution Record**
Contains:
- **Who** is absent (Absent Teacher)
- **When** they're absent (Date)
- **What** needs coverage (Class, Section, Subject, Period)
- **Who** will cover (Substitute Teacher)
- **Why** they're absent (Reason)
- **Status** (Pending/Assigned/Approved/Cancelled)

### 2. **Smart Suggestion**
System automatically finds the best substitute based on:
- ✅ Available in that period (not teaching another class)
- ✅ Teaches the same subject (preferred)
- ✅ Has taught this class before (preferred)
- ✅ Not overloaded with substitutions (max 2 per day)

### 3. **Status Workflow**
```
PENDING → ASSIGNED → APPROVED
   ↓
CANCELLED (if no longer needed)
```

---

## 🔢 Scoring System (How System Picks Best Substitute)

| Criteria | Points | Example |
|----------|--------|---------|
| Same Subject | 50 | Math teacher for Math class |
| Class Experience | 30 | Has taught Class 10 before |
| Not Overloaded | 20 | Has ≤2 substitutions today |
| **Maximum** | **100** | Perfect match! |

**Example:**
- Teacher A: Math teacher, taught Class 10, 1 substitution = **100 points** ⭐
- Teacher B: Science teacher, taught Class 10, 0 substitutions = **50 points**
- Teacher C: Math teacher, never taught Class 10, 3 substitutions = **50 points**

**Winner:** Teacher A (best match)

---

## 📋 Common Actions

### Create Substitution
1. Go to: **Teacher Substitutions → Create**
2. Fill form:
   - Date: When absent
   - Absent Teacher: Who's absent
   - Class/Section: Which class
   - Subject: Which subject
   - Period: Which period (1-10)
   - Reason: Why absent
3. Click **Create**
4. System suggests best substitute automatically

### View Today's Substitutions
1. Go to: **Teacher Substitutions → Today**
2. See all substitutions for today
3. Organized by period number

### Assign Substitute
1. Go to: **Teacher Substitutions → List**
2. Click **Edit** on a substitution
3. Select substitute teacher
4. Click **Assign**
5. Status changes to "Assigned"

### Approve Substitution
1. Go to: **Teacher Substitutions → List**
2. Click **Approve** button
3. Status changes to "Approved"
4. Substitution confirmed

---

## 🔍 Filters Available

You can filter substitutions by:
- **Date** (default: today)
- **Status** (pending/assigned/approved/cancelled)
- **Class** (which class)
- **Teacher** (absent or substitute)

---

## 📊 Reports Available

### 1. Today's Substitutions
Shows all substitutions happening today, period by period.

### 2. Absence Overview
Shows:
- Which teachers are absent today
- Which teachers are doing substitutions today
- How many periods each

---

## 💡 Key Features

✅ **Automatic Suggestion** - System picks best substitute  
✅ **Conflict Prevention** - Won't suggest busy teachers  
✅ **Workload Balance** - Limits substitutions per teacher  
✅ **Audit Trail** - Tracks who created/updated  
✅ **Flexible Status** - Can change or cancel anytime  
✅ **Date-based** - Plan ahead for future absences  
✅ **Period-specific** - Precise scheduling (Period 1-10)  

---

## 🎓 Real-World Example

**Scenario:** Mr. Sharma (Math teacher) is sick on Monday

**What Happens:**

1. **Monday Morning (8:00 AM)**
   - Admin creates substitution record
   - Date: Today
   - Absent: Mr. Sharma
   - Class: 10-A
   - Subject: Mathematics
   - Period: 3 (10:00-11:00 AM)
   - Reason: "Sick leave"

2. **System Suggests (8:01 AM)**
   - Checks all teachers
   - Finds Mrs. Gupta:
     * Teaches Math ✓
     * Free in Period 3 ✓
     * Has taught Class 10 ✓
     * Only 1 substitution today ✓
   - Score: 100 points
   - Suggests Mrs. Gupta

3. **Admin Reviews (8:05 AM)**
   - Sees suggestion: Mrs. Gupta
   - Approves
   - Status: Approved

4. **Period 3 (10:00 AM)**
   - Mrs. Gupta goes to Class 10-A
   - Teaches Math
   - Students don't miss class ✓

5. **Record Kept**
   - Audit trail maintained
   - Can generate reports later
   - Track substitution patterns

---

## 🚨 Important Rules

1. **One Period at a Time**: A teacher can't be in two places at once
2. **Max 2 Substitutions/Day**: Prevents teacher overload
3. **Must Be Available**: System checks availability automatically
4. **Status Required**: Every substitution has a status
5. **Audit Trail**: All changes are tracked

---

## 📱 Where to Find It

**Main Menu:**
```
Admin Dashboard
  └── Teacher Management
      └── Teacher Substitutions
          ├── List All
          ├── Create New
          ├── Today's Substitutions
          └── Absence Overview
```

---

## 🎯 Quick Tips

💡 **Tip 1:** Create substitutions as soon as you know about an absence  
💡 **Tip 2:** Check "Today's Substitutions" every morning  
💡 **Tip 3:** Use filters to find specific substitutions quickly  
💡 **Tip 4:** Review "Absence Overview" to see patterns  
💡 **Tip 5:** Trust the system's suggestions - they're smart!  

---

## ❓ Common Questions

**Q: Can I assign a substitute manually?**  
A: Yes! The system suggests, but you can choose any available teacher.

**Q: What if the suggested teacher is not suitable?**  
A: Just select a different teacher from the dropdown when editing.

**Q: Can I create substitutions for future dates?**  
A: Yes! You can plan ahead for known absences.

**Q: What if a teacher returns unexpectedly?**  
A: Just cancel the substitution. Status changes to "Cancelled".

**Q: How do I see all substitutions for a specific teacher?**  
A: Use the teacher filter on the main list page.

**Q: Can a teacher see their substitution schedule?**  
A: Currently admin-only. Teacher portal can be added later.

---

## 🎓 Summary

**Teacher Substitution System = Smart Absence Management**

It automatically:
- Finds best substitute teachers
- Prevents scheduling conflicts
- Balances workload
- Maintains records
- Provides daily overview

**Result:** No class is left without a teacher! ✅

---

**Created:** February 6, 2026  
**Status:** Documentation Only - No Changes Made  
**For:** Understanding the system
