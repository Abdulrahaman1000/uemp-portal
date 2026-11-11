import type { AdminSection } from "./sections";
import ActivityLog from "./ActivityLog";
import Courses from "./Courses";
import Departments from "./Departments";
import Faculties from "./Faculties";
import Oversight from "./Oversight";
import Overview from "./Overview";
import Programs from "./Programs";
import Registrations from "./Registrations";
import Reports from "./Reports";
import Roles from "./Roles";
import Sessions from "./Sessions";
import Users from "./Users";

export type { AdminSection } from "./sections";

/** Routes a super admin menu section to its page. All data comes from /api/admin/*. */
export default function AdminPages({ section = "overview", onSectionChange }: { section?: string; onSectionChange?: (section: string) => void }) {
  const go = (next: AdminSection) => onSectionChange?.(next);
  switch (section) {
    case "faculties": return <Faculties />;
    case "departments": return <Departments />;
    case "programs": return <Programs />;
    case "sessions": return <Sessions />;
    case "users": return <Users />;
    case "roles": return <Roles />;
    case "courses": return <Courses />;
    case "registrations": return <Registrations />;
    case "oversight": return <Oversight />;
    case "reports": return <Reports />;
    case "activity-log": return <ActivityLog />;
    default: return <Overview go={go} />;
  }
}
