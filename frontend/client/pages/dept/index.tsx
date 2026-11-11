import Approvals from "./Approvals";
import Assignments from "./Assignments";
import Courses from "./Courses";
import Lecturers from "./Lecturers";
import Overview from "./Overview";
import Programs from "./Programs";
import Registrations from "./Registrations";
import Reports from "./Reports";
import Students from "./Students";

export type DeptSection = "overview" | "students" | "lecturers" | "courses" | "assignments" | "programs" | "registrations" | "approvals" | "reports";

/** Routes a department admin menu section to its page. All data comes from /api/dept/*. */
export default function DeptPages({ section }: { section: string }) {
  switch (section) {
    case "students": return <Students />;
    case "lecturers": return <Lecturers />;
    case "courses": return <Courses />;
    case "assignments": return <Assignments />;
    case "programs": return <Programs />;
    case "registrations": return <Registrations />;
    case "approvals": return <Approvals />;
    case "reports": return <Reports />;
    default: return <Overview />;
  }
}
