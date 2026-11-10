import AdminScreens, { type AdminScreensProps } from "../../components/AdminScreens";
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

/**
 * Routes an admin section to its page. Sections that have been migrated to the
 * API render here. Everything else still falls back to the legacy AdminScreens
 * so the app keeps working while pages are moved one at a time.
 */
export default function AdminPages(props: AdminScreensProps) {
  const { role = "department", section = "overview", onSectionChange } = props;
  const go = (next: AdminSection) => onSectionChange?.(next);

  if (role === "super-admin") {
    switch (section) {
      case "overview": return <Overview go={go} />;
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
      default: break;
    }
  }
  return <AdminScreens {...props} />;
}
