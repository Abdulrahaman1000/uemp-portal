import Overview from "./Overview";
import Profile from "./Profile";
import Registration from "./Registration";
import MyCourses from "./MyCourses";
import Results from "./Results";
import History from "./History";

export type StudentSection = "overview" | "profile" | "registration" | "courses" | "results" | "history";

/** Routes a student menu section to its page. All data comes from /api/student/*. */
export default function StudentPages({ section }: { section: string }) {
  switch (section) {
    case "profile": return <Profile />;
    case "registration": return <Registration />;
    case "courses": return <MyCourses />;
    case "results": return <Results />;
    case "history": return <History />;
    default: return <Overview />;
  }
}
