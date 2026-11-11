import Courses from "./Courses";
import Overview from "./Overview";
import ResultEntry from "./ResultEntry";
import Submissions from "./Submissions";

export type LecturerSection = "overview" | "courses" | "results" | "submissions";

/** Routes a lecturer menu section to its page. All data comes from /api/lecturer/*. */
export default function LecturerPages({ section }: { section: string }) {
  switch (section) {
    case "courses": return <Courses />;
    case "results": return <ResultEntry />;
    case "submissions": return <Submissions />;
    default: return <Overview />;
  }
}
