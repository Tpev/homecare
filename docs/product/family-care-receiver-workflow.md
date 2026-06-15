# Care Receiver Side Workflow

This map is the family / care receiver journey for posting care, hiring, payment protection, visit management, approval, and rebooking.

```mermaid
flowchart TD
    A["New user lands on LoLo"] --> B{"Has an account?"}
    B -- "No" --> C["Start care / Register"]
    B -- "Yes" --> D["Sign in"]
    C --> E["Family home"]
    D --> E

    E --> F{"What does the user need now?"}
    F -- "New help" --> G["Start care request"]
    F -- "Same caregiver again" --> R1["Book again"]
    F -- "Weekly care" --> W1["Weekly care plan"]
    F -- "Existing item needs action" --> H["Open Care hub"]

    G --> G1["Answer simple questions"]
    G1 --> G2["Who receives care"]
    G2 --> G3["Care tasks"]
    G3 --> G4["Start day"]
    G4 --> G5["Start time"]
    G5 --> G6["Duration"]
    G6 --> G7["Care address"]
    G7 --> G8["Publish request"]

    G8 --> I["Request detail"]
    I --> I1{"Caregiver source"}
    I1 -- "Caregivers apply" --> J["Review caregivers"]
    I1 -- "User invites caregiver" --> K["Invite caregiver"]
    I1 -- "Browse first" --> L["Find caregivers"]
    L --> K
    K --> M["Caregiver accepts invite"]
    J --> N["View profile, reviews, badges, notes"]
    M --> N

    N --> O{"Ready to hire?"}
    O -- "No" --> O1["Save for later / chat / decline"]
    O1 --> J
    O -- "Yes" --> P["Hire selected caregiver"]

    P --> Q{"Billing ready?"}
    Q -- "No card" --> Q1["Add card in Billing"]
    Q1 --> P
    Q -- "Card saved" --> Q2["Pre-authorize expected visit amount"]
    Q2 --> Q3{"Stripe needs 3DS?"}
    Q3 -- "Yes" --> Q4["Confirm secure card challenge"]
    Q4 --> Q5{"Authorization succeeds?"}
    Q3 -- "No" --> Q5
    Q5 -- "No" --> Q6["Show payment attention and retry/update card"]
    Q6 --> Q1
    Q5 -- "Yes" --> S["Visit scheduled"]

    S --> T{"Before visit"}
    T -- "Need change" --> T1["Request reschedule/cancel"]
    T -- "Caregiver does not arrive" --> T2{"30 minutes after start?"}
    T2 -- "No" --> T3["Explain no-show unlock time"]
    T2 -- "Yes" --> T4["Mark caregiver no-show"]
    T -- "Everything ok" --> U["Caregiver checks in"]

    U --> V["Visit in progress"]
    V --> V1["Message caregiver / view location / support"]
    V --> W["Caregiver checks out and submits timesheet"]

    W --> X["Family reviews hours"]
    X --> X1{"Hours look right?"}
    X1 -- "No" --> X2["Question hours / support / dispute"]
    X1 -- "Yes" --> Y["Approve timesheet"]

    Y --> Z["Capture final payment"]
    Z --> Z1{"Authorized amount enough?"}
    Z1 -- "No" --> Z2["Collect overage / retry payment"]
    Z1 -- "Yes" --> Z3["Payment captured"]
    Z2 --> Z3
    Z3 --> Z4["Caregiver payout moves forward through Stripe Connect"]
    Z4 --> AA["Visit closed"]
    AA --> AB["Leave review"]

    AB --> AC{"Need more care?"}
    AC -- "Same caregiver once" --> R1
    AC -- "Same caregiver regularly" --> W1
    AC -- "Different caregiver" --> G
    AC -- "No" --> E

    R1 --> R2["Use previous recipient, address, tasks, and caregiver"]
    R2 --> R3["Choose new day, time, and duration"]
    R3 --> R4["Send direct invite to same caregiver"]
    R4 --> M

    W1 --> W2["Choose days and recurring time"]
    W2 --> W3["Send regular care offer"]
    W3 --> W4{"Caregiver response"}
    W4 -- "Accepts" --> S
    W4 -- "Counters" --> W5["Review counter schedule"]
    W5 --> W6{"Accept counter?"}
    W6 -- "Yes" --> S
    W6 -- "No" --> W2
```

## Button Rules

- One primary action per screen section: the button should match the next real decision.
- Risky actions stay behind a clear disclosure, such as "Need to stop this request?"
- Payment actions say exactly what will happen: pre-authorize, confirm authorization, approve timesheet, capture payment.
- Rebooking should never ask the user to start over. It should reuse caregiver, recipient, address, care tasks, and only ask for the next schedule.
- Older users should see plain verbs: Start care, Review caregivers, Hire Caroline, Open visit, Review hours, Book Caroline again.
