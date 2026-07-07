# Minimal setup and usage

## Setup

1.  Requires a minimum of three users:
    1.  tutormanager1
    2.  tutor1
    3.  student1
2.  Create course *course1* and enrol *student1*.
    1.  Add an assignment activity *assignment1* with a due date. The defaults are suitable.
3.  As **admin**, assign *tutormanager1* the system role *Education tutor manager*.

## Allocate students to tutors

1.  As **tutormanager1**, from the user menu, select *Manage Education Tutor allocations*.
    1.  In the *Tutor* field, select tutor1.
    2.  In the *Students* field, select student1.
    3.  Click [Add allocation].

## View the tutor dashboard

1.  As **tutor1**, from the user menu, select *Education Tutor Dashboard*.
    1.  You should see a card with *student1*, containing *course1*, which contains *assignment1* with status *Not submitted*.
    2.  Adjacent to *course1*, you should see [Login as].
    3.  Adjacent to *assignment1*, you should see [Submit].

## Take actions as the allocated student

1.  Submit an assignment on behalf:
    1.  As **tutor1**, from the *Education Tutor Dashboard*, click [Submit]. A submission page will be displayed.
    2.  Add a file and click [Submit on behalf of student]. A confirmation will be displayed.
2.  Login as:
    1.  As **tutor1**, from the *Education Tutor Dashboard*, click [Login as]. You will be logged in as *student1* and taken to *course1*.
    2.  Observe that *assignment1* has been submitted.
3.  Return to tutor1:
    1.  From the user menu, select *Return to my account*.
