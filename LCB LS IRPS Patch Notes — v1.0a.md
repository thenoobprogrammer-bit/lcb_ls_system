**LCB LS IRPS Patch Notes — v1.0a**



***Admin \& Employee Panels***



Applies to:



* Admin Panel
* Employee Panel (Sound, Lights, Crew)



***Date Format Standardization***



* All system dates must now use full text format.
* Example:

  * **`May 6, 2026`**
  * **`5/6/2026`**



\---



**Inventory System Updates**



***Equipment Categories***



Each inventory item must now belong to a category.



***Example Categories***



* Speaker
* Mixer
* Lights
* Light Controller
* Wires



\---



**Wire Length Support**



If the selected category is **Wires**, the system should display a wire length field during inventory creation/editing.



***Preset Length Options***



* 2 meters
* 4 meters
* 5 meters
* 10 meters
* 20 meters



***Additional Behavior***



* Users may also manually input custom wire lengths.
* If the selected category is NOT **Wires**, the meters/length field must remain hidden.



\---



**Category Management (NEW)**



Add a dedicated **Category Tab** where Admins and authorized Employees can manage equipment categories.



***Required Features***



* Create Category
* Read/View Categories
* Update Category
* Delete Category



**Database Requirement**



Implement category support directly into the database structure.



**Package System Updates**



***Equipment Quantity Support***



Included equipment inside packages must now support quantities.



**Example**



If inventory has:



* 2 Mixers



The Admin may include:



* 1 Mixer
* or 2 Mixers



inside a package configuration.



\---



**Inventory Availability Validation**



If equipment stock is insufficient:



* The item must automatically become disabled or greyed out in package selection.



\---



**Events System Updates**



***Event Creation***



Admins can now create events directly from the system.



\---



**Automatic Approval Option**



Events may optionally be:



* Automatically Approved upon creation.



\---



**Crew Assignment**



During event creation, the system must ask/select:



* Assigned Crew Members
* Sound Personnel
* Light Personnel



\---



**Booking Calendar Layout Fix**



Move the:



* Booking Calendar



Below:



* Booking Requests
* Event Requests



for cleaner dashboard organization.



\---



**Whole System Dashboard Updates**



***Notification System***



The notification icon must now be clickable.



***Notification Behavior***



When clicked:



* Display notifications based on user role:



&#x09;Admin

&#x09;Employee

&#x09;Customer



***UI Requirement***



Use a pop-up modal for cleaner and modern UI implementation.



\---



**Profile Button Behavior**



The profile button must redirect users to:



* Edit Profile Page



instead of only viewing profile information.



\---



**Patch Version**



**LCB LS IRPS Patch v1.0a**



***Focus Areas***



* Inventory Management
* Package Validation
* Event Handling
* Dashboard UI Improvements
* Category System Integration
* Better User Workflow and Data Consistency



