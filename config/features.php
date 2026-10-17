<?php

/*
|--------------------------------------------------------------------------
| Feature catalogue
|--------------------------------------------------------------------------
|
| The modules a "New feature" announcement can point people to. The key is
| the stable feature_key stored on announcements and sent in notification
| and push data; the mobile app maps the same keys to its own screens, so
| never rename a key once it has been used.
|
|   label : shown in the admin feature select
|   route : web route name the in-app "Explore now" button opens
|   icon  : Font Awesome class shown in the web notification centre
|
*/

return [
    'notes' => ['label' => 'Notes', 'route' => 'notes.index', 'icon' => 'fa-solid fa-note-sticky'],
    'reminders' => ['label' => 'Reminders', 'route' => 'reminders.index', 'icon' => 'fa-solid fa-bell'],
    'daily_planner' => ['label' => 'Daily Planner', 'route' => 'daily-planner.index', 'icon' => 'fa-solid fa-calendar-check'],
    'annual_plans' => ['label' => 'Annual Plans', 'route' => 'annual-plans.index', 'icon' => 'fa-solid fa-bullseye'],
    'personal_goals' => ['label' => 'Goals', 'route' => 'personal-goals.index', 'icon' => 'fa-solid fa-crosshairs'],
    'ai_planner' => ['label' => 'AI Planner', 'route' => 'ai-plans.index', 'icon' => 'fa-solid fa-robot'],
    'meetings' => ['label' => 'Meetings', 'route' => 'meetings.index', 'icon' => 'fa-solid fa-calendar-days'],
    'projects' => ['label' => 'Projects', 'route' => 'projects.index', 'icon' => 'fa-solid fa-diagram-project'],
    'project_tasks' => ['label' => 'Project Tasks', 'route' => 'project-tasks.index', 'icon' => 'fa-solid fa-clipboard-check'],
    'team_chat' => ['label' => 'Team Chat', 'route' => 'team-chat.index', 'icon' => 'fa-solid fa-comments'],
    'financial_planner' => ['label' => 'Financial Planner', 'route' => 'financial-planner.index', 'icon' => 'fa-solid fa-chart-line'],
    'incomes' => ['label' => 'Income', 'route' => 'incomes.index', 'icon' => 'fa-solid fa-money-bill-trend-up'],
    'business_invoices' => ['label' => 'Business Invoices', 'route' => 'business-invoices.index', 'icon' => 'fa-solid fa-file-invoice-dollar'],
    'budgets' => ['label' => 'Budgets', 'route' => 'budgets.index', 'icon' => 'fa-solid fa-wallet'],
    'expenses' => ['label' => 'Expenses', 'route' => 'expenses.index', 'icon' => 'fa-solid fa-receipt'],
    'debts' => ['label' => 'Debts', 'route' => 'debts.index', 'icon' => 'fa-solid fa-hand-holding-dollar'],
    'savings_goals' => ['label' => 'Savings Goals', 'route' => 'savings-goals.index', 'icon' => 'fa-solid fa-piggy-bank'],
    'wellbeing' => ['label' => 'Daily Wellbeing', 'route' => 'wellbeing.index', 'icon' => 'fa-solid fa-droplet'],
    'diet' => ['label' => 'Diet', 'route' => 'diet-logs.index', 'icon' => 'fa-solid fa-utensils'],
    'exercise' => ['label' => 'Exercise', 'route' => 'exercise-logs.index', 'icon' => 'fa-solid fa-person-running'],
    'sleep' => ['label' => 'Sleep', 'route' => 'sleep-logs.index', 'icon' => 'fa-solid fa-bed'],
    'health_checkups' => ['label' => 'Health Checkups', 'route' => 'health-checkups.index', 'icon' => 'fa-solid fa-stethoscope'],
    'education_plans' => ['label' => 'Education', 'route' => 'education-plans.index', 'icon' => 'fa-solid fa-graduation-cap'],
    'network_contacts' => ['label' => 'Network', 'route' => 'network-contacts.index', 'icon' => 'fa-solid fa-people-arrows'],
    'relationships' => ['label' => 'Relationships', 'route' => 'relationships.index', 'icon' => 'fa-solid fa-heart'],
    'spiritual_growth' => ['label' => 'Spiritual Growth', 'route' => 'spiritual-practices.index', 'icon' => 'fa-solid fa-hands-praying'],
    'social_media_planner' => ['label' => 'Social Media Planner', 'route' => 'social-media-planner.index', 'icon' => 'fa-solid fa-share-nodes'],
    'business_card' => ['label' => 'My Business Card', 'route' => 'business-card.edit', 'icon' => 'fa-solid fa-id-card'],
    'signatures' => ['label' => 'Signatures', 'route' => 'signature.show', 'icon' => 'fa-solid fa-signature'],
];
