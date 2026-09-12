import { useEffect, useState } from "react";
export type MenuItem = { id: string; label: string; planned?: boolean };
export type MenuGroup = {
  id: string;
  label: string;
  icon: string;
  items: MenuItem[];
};
function MenuIcon({ name }: { name: string }) {
  const path = name.includes("communications")
    ? "M3 5h18v14H3z M3 6l9 7 9-7"
    : name.includes("settings")
      ? "M4 6h16 M4 12h16 M4 18h16 M8 3v6 M16 9v6 M10 15v6"
      : name.includes("people")
        ? "M8 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8 M2 21v-3a6 6 0 0 1 12 0v3 M16 5a4 4 0 0 1 0 8 M17 16a5 5 0 0 1 5 5"
        : name.includes("attendance")
          ? "M4 5h16v16H4z M8 2v6 M16 2v6 M4 10h16 M8 15l3 3 5-5"
          : name.includes("history")
            ? "M4 7a9 9 0 1 1-1 9 M3 3v5h5 M12 7v6l4 2"
            : name.includes("exams") ||
                name.includes("learning") ||
                name.includes("academics")
              ? "M5 3h14v18H5z M9 8h6 M9 12h6 M9 16h4"
              : "M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z";
  return (
    <svg
      className="menu-symbol"
      aria-hidden="true"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.6"
      strokeLinecap="round"
      strokeLinejoin="round"
    >
      <path d={path} />
    </svg>
  );
}
export function GroupedMenu({
  groups,
  active,
  onSelect,
  label,
}: {
  groups: MenuGroup[];
  active: string;
  onSelect: (id: string) => void;
  label: string;
}) {
  const [expanded, setExpanded] = useState<Record<string, boolean>>({});
  const selectedGroup = groups.find((g) =>
    g.items.some((i) => i.id === active),
  )?.id;
  useEffect(() => {
    if (selectedGroup) setExpanded((v) => ({ ...v, [selectedGroup]: true }));
  }, [selectedGroup]);
  return (
    <nav className="grouped-menu" aria-label={label}>
      {groups
        .filter((g) => g.items.length)
        .map((group) => (
          <section className="menu-group" key={group.id}>
            <button
              type="button"
              className={`menu-group-toggle ${group.id === selectedGroup ? "is-current" : ""}`}
              aria-expanded={!!expanded[group.id]}
              aria-controls={`menu-${group.id}`}
              onClick={() =>
                setExpanded((v) => ({ ...v, [group.id]: !v[group.id] }))
              }
            >
              <MenuIcon name={group.id} />
              <span>{group.label}</span>
              <span className="menu-chevron" aria-hidden="true">
                {expanded[group.id] ? "−" : "+"}
              </span>
            </button>
            <div
              id={`menu-${group.id}`}
              className="menu-children"
              hidden={!expanded[group.id]}
            >
              {group.items.map((item) => (
                <button
                  type="button"
                  key={item.id}
                  className={active === item.id ? "nav-active" : ""}
                  aria-current={active === item.id ? "page" : undefined}
                  onClick={() => onSelect(item.id)}
                >
                  <span>{item.label}</span>
                  {item.planned && (
                    <small className="menu-planned">Planned</small>
                  )}
                </button>
              ))}
            </div>
          </section>
        ))}
    </nav>
  );
}
export const plannedPages: Record<
  string,
  { title: string; description: string; items: string[]; academics?: boolean }
> = {
  "Exam workspace": {
    title: "Exams · ExamElite",
    description:
      "Exam creation will start from a class or section and continue through ExamElite. The integration is not connected in Tech4Learn yet.",
    items: [
      "Select a class or section and its students",
      "Configure question types and build the exam in ExamElite",
      "Return to the same class context for results",
    ],
    academics: true,
  },
  "Exam results": {
    title: "Results & performance",
    description:
      "ExamElite results will appear against the correct student and class after identity mapping and result synchronisation are implemented.",
    items: [
      "Exam attempts and scores",
      "Student performance history",
      "Result synchronisation status",
    ],
    academics: true,
  },
  "FLN workspace": {
    title: "FLN & learning",
    description:
      "The learning assessment workspace is planned. Activities and progress records are not available yet.",
    items: [
      "Reading, language and numeracy activities",
      "Teacher review and follow-up",
      "Student learning progress",
    ],
  },
  "Email settings": {
    title: "Email settings",
    description:
      "Email delivery configuration is planned. No outgoing email provider is configured from this screen.",
    items: [
      "Provider connection and sender identity",
      "Organisation reply-to address",
      "Connection test and delivery status",
    ],
  },
  "Email templates": {
    title: "Email templates",
    description:
      "Reusable email templates are planned. Invitations currently use a private link that an administrator shares manually.",
    items: [
      "Invitation and welcome messages",
      "Exam and attendance notifications",
      "Preview before sending",
    ],
  },
  "Message settings": {
    title: "Messaging settings",
    description:
      "SMS and messaging integrations are planned. This page does not send messages or store provider credentials.",
    items: [
      "Messaging provider connections",
      "Approved sender and recipient preferences",
      "Delivery and failure history",
    ],
  },
  "Delivery history": {
    title: "Delivery history",
    description:
      "Email and message delivery tracking will be available when sending integrations are implemented.",
    items: [
      "Sent, pending and failed deliveries",
      "Recipient and channel filters",
      "Controlled retry of failed deliveries",
    ],
  },
};
export function organisationMenu(
  centreLabel: string,
  tabs: string[],
  upcoming: string[],
): MenuGroup[] {
  const item = (id: string, label = id) => ({
    id,
    label,
    planned: upcoming.includes(id),
  });
  return [
    {
      id: "org-overview",
      label: "Dashboard",
      icon: "▦",
      items: [item("Daily overview")],
    },
    {
      id: "org-academics",
      label: "Academics",
      icon: "▤",
      items: [
        item("Centres", `${centreLabel}s`),
        item("Groups", "Classes & sections"),
        item("Learners", "Students / enrolment"),
      ],
    },
    {
      id: "org-attendance",
      label: "Attendance",
      icon: "◎",
      items: [
        item("Attendance", "Daily register"),
        item("Photo capture", "Take classroom photos"),
      ],
    },
    {
      id: "org-exams",
      label: "Exams & results",
      icon: "▥",
      items: [
        item("Exam workspace", "ExamElite workspace"),
        item("Exam results", "Results & performance"),
      ],
    },
    {
      id: "org-learning",
      label: "FLN & learning",
      icon: "◇",
      items: [item("FLN workspace", "Activities & progress")],
    },
    {
      id: "org-people",
      label: "Staff & permissions",
      icon: "♙",
      items: [
        item("Team", "Staff directory"),
        item("Roles", "Roles & permissions"),
      ],
    },
    {
      id: "org-communications",
      label: "Email & messaging",
      icon: "✉",
      items: [
        item("Email settings"),
        item("Email templates"),
        item("Message settings", "Messaging settings"),
        item("Delivery history"),
      ],
    },
    {
      id: "org-settings",
      label: "Settings",
      icon: "⚙",
      items: [
        item("Profile", "Organisation profile"),
        item("Setup", "Branding, modules & domains"),
        item("Custom fields"),
        item("AI connections"),
      ],
    },
    {
      id: "org-history",
      label: "Audit & history",
      icon: "◷",
      items: [item("History", "Change history")],
    },
  ]
    .map((g) => ({
      ...g,
      items: g.items.filter(
        (i) => tabs.includes(i.id) || upcoming.includes(i.id),
      ),
    }))
    .filter((g) => g.items.length);
}
