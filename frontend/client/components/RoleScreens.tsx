import { useEffect, useState, type FormEvent, type ReactNode } from "react";
import {
  ArrowRight,
  BookOpen,
  BookOpenCheck,
  CalendarDays,
  Check,
  CheckCircle2,
  ChevronDown,
  ClipboardCheck,
  Clock3,
  FileText,
  GraduationCap,
  LayoutDashboard,
  Search,
  Sparkles,
  UserRound,
  Users,
  X,
} from "lucide-react";

export type PortalRole = "student" | "lecturer";
export type RoleSection =
  | "overview"
  | "profile"
  | "registration"
  | "courses"
  | "results"
  | "history"
  | "submissions";
export interface RoleScreensProps {
  role?: PortalRole;
  /** Pass a section to control navigation from a parent. */
  section?: RoleSection;
  onSectionChange?: (section: RoleSection) => void;
  /** Hide the built-in shell when embedding in an existing portal. */
  hideNavigation?: boolean;
}

type Course = {
  code: string;
  title: string;
  faculty: string;
  credits: number;
  schedule: string;
  color: string;
  initials: string;
  progress: number;
};
type Submission = {
  id: number;
  name: string;
  studentId: string;
  assignment: string;
  course: string;
  submitted: string;
  status: "Pending" | "Reviewed";
};

const courses: Course[] = [
  {
    code: "CS 301",
    title: "Data Structures & Algorithms",
    faculty: "Dr. Eleanor Hayes",
    credits: 4,
    schedule: "Mon, Wed · 10:00 AM",
    color: "bg-[#e9e7fa] text-[#6255a5]",
    initials: "DS",
    progress: 72,
  },
  {
    code: "CS 315",
    title: "Database Management Systems",
    faculty: "Prof. Daniel Kim",
    credits: 3,
    schedule: "Tue, Thu · 11:30 AM",
    color: "bg-[#e3f1e9] text-[#4c866b]",
    initials: "DB",
    progress: 58,
  },
  {
    code: "MATH 220",
    title: "Linear Algebra",
    faculty: "Dr. Sarah Mitchell",
    credits: 3,
    schedule: "Mon, Fri · 1:00 PM",
    color: "bg-[#fbece1] text-[#bd7956]",
    initials: "LA",
    progress: 64,
  },
  {
    code: "CS 340",
    title: "Human–Computer Interaction",
    faculty: "Dr. Eleanor Hayes",
    credits: 3,
    schedule: "Wed, Fri · 2:30 PM",
    color: "bg-[#e5eef9] text-[#6687ad]",
    initials: "HC",
    progress: 41,
  },
  {
    code: "CS 360",
    title: "Introduction to Machine Learning",
    faculty: "Dr. Priya Shah",
    credits: 4,
    schedule: "Tue, Thu · 2:00 PM",
    color: "bg-[#fae9ed] text-[#b77587]",
    initials: "ML",
    progress: 0,
  },
  {
    code: "DES 210",
    title: "Design Thinking",
    faculty: "Prof. Maya Chen",
    credits: 3,
    schedule: "Fri · 10:00 AM",
    color: "bg-[#f6efd9] text-[#a18b4e]",
    initials: "DT",
    progress: 0,
  },
];
const pastResults = [
  {
    code: "CS 201",
    course: "Object-Oriented Programming",
    credits: 4,
    grade: "5.0",
    score: 74,
  },
  {
    code: "MATH 210",
    course: "Discrete Mathematics",
    credits: 3,
    grade: "4.0",
    score: 68,
  },
  {
    code: "CS 230",
    course: "Computer Architecture",
    credits: 3,
    grade: "3.0",
    score: 58,
  },
  {
    code: "ENG 102",
    course: "Academic Writing",
    credits: 3,
    grade: "5.0",
    score: 82,
  },
];
// Illustrative five-point conversion for the mock results, not a university policy.
function gradePoint(score: number): string {
  return (
    score >= 70
      ? 5
      : score >= 60
        ? 4
        : score >= 50
          ? 3
          : score >= 45
            ? 2
            : score >= 40
              ? 1
              : 0
  ).toFixed(1);
}
const initialSubmissions: Submission[] = [
  {
    id: 1,
    name: "Olivia Bennett",
    studentId: "STU-2024-018",
    assignment: "Algorithm analysis",
    course: "CS 301",
    submitted: "Mar 18, 2025",
    status: "Pending",
  },
  {
    id: 2,
    name: "Noah Williams",
    studentId: "STU-2024-042",
    assignment: "Algorithm analysis",
    course: "CS 301",
    submitted: "Mar 18, 2025",
    status: "Pending",
  },
  {
    id: 3,
    name: "Ava Robinson",
    studentId: "STU-2024-067",
    assignment: "Usability case study",
    course: "CS 340",
    submitted: "Mar 17, 2025",
    status: "Reviewed",
  },
  {
    id: 4,
    name: "Liam Carter",
    studentId: "STU-2024-023",
    assignment: "Algorithm analysis",
    course: "CS 301",
    submitted: "Mar 16, 2025",
    status: "Pending",
  },
];

const studentNav: {
  id: RoleSection;
  label: string;
  icon: typeof LayoutDashboard;
}[] = [
  { id: "overview", label: "Overview", icon: LayoutDashboard },
  { id: "profile", label: "My profile", icon: UserRound },
  { id: "registration", label: "Course registration", icon: ClipboardCheck },
  { id: "courses", label: "My courses", icon: BookOpen },
  { id: "results", label: "Results", icon: GraduationCap },
  { id: "history", label: "Academic history", icon: Clock3 },
];
const lecturerNav: {
  id: RoleSection;
  label: string;
  icon: typeof LayoutDashboard;
}[] = [
  { id: "overview", label: "Overview", icon: LayoutDashboard },
  { id: "courses", label: "My courses", icon: BookOpen },
  { id: "results", label: "Result entry", icon: GraduationCap },
  { id: "submissions", label: "Submissions", icon: FileText },
];
const panel =
  "rounded-2xl border border-[#e9ece9] bg-white shadow-[0_4px_24px_rgba(30,48,38,0.035)]";
const primaryButton =
  "inline-flex items-center justify-center gap-2 rounded-lg bg-[#315f4c] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#244b3b] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#315f4c] disabled:cursor-not-allowed disabled:opacity-50";
const secondaryButton =
  "inline-flex items-center justify-center gap-2 rounded-lg border border-[#e1e6e2] bg-white px-4 py-2.5 text-sm font-semibold text-[#34483c] transition hover:bg-[#f5f8f5] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#315f4c]";
const inputClass =
  "w-full rounded-lg border border-[#dfe6e0] bg-white px-3.5 py-2.5 text-sm text-[#26392e] outline-none transition placeholder:text-[#a0aaa2] focus:border-[#6f9b7e] focus:ring-2 focus:ring-[#e1eee4]";

function SectionHeading({
  eyebrow,
  title,
  description,
  action,
}: {
  eyebrow?: string;
  title: string;
  description: string;
  action?: ReactNode;
}) {
  return (
    <div className="mb-7 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
      <div>
        {eyebrow && (
          <p className="mb-2 text-xs font-bold uppercase tracking-[0.16em] text-[#789081]">
            {eyebrow}
          </p>
        )}
        <h1 className="text-2xl font-semibold tracking-tight text-[#20372a] sm:text-[30px]">
          {title}
        </h1>
        <p className="mt-1.5 text-sm leading-6 text-[#7d8b81]">{description}</p>
      </div>
      {action}
    </div>
  );
}
function CardTitle({
  title,
  subtitle,
  action,
}: {
  title: string;
  subtitle?: string;
  action?: ReactNode;
}) {
  return (
    <div className="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h2 className="text-base font-semibold text-[#243b2d]">{title}</h2>
        {subtitle && (
          <p className="mt-1 text-xs leading-5 text-[#94a097]">{subtitle}</p>
        )}
      </div>
      {action}
    </div>
  );
}
function Badge({
  children,
  green = false,
}: {
  children: ReactNode;
  green?: boolean;
}) {
  return (
    <span
      className={`inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold ${green ? "bg-[#e8f3eb] text-[#538468]" : "bg-[#f5f5ef] text-[#9a8c60]"}`}
    >
      {children}
    </span>
  );
}
function CourseIcon({ course }: { course: Course }) {
  return (
    <div
      className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-xs font-bold ${course.color}`}
    >
      {course.initials}
    </div>
  );
}
function Stat({
  icon,
  label,
  value,
  note,
}: {
  icon: ReactNode;
  label: string;
  value: string | number;
  note: string;
}) {
  return (
    <div className={`${panel} p-5`}>
      <div className="flex items-center justify-between">
        <span className="text-xs font-medium text-[#849187]">{label}</span>
        <span className="rounded-lg bg-[#f0f6f1] p-2 text-[#6d9578]">
          {icon}
        </span>
      </div>
      <div className="mt-3 text-[27px] font-semibold tracking-tight text-[#243b2d]">
        {value}
      </div>
      <p className="mt-1 text-xs text-[#9aa69c]">{note}</p>
    </div>
  );
}
function Empty({ text }: { text: string }) {
  return <div className="py-12 text-center text-sm text-[#8c9a90]">{text}</div>;
}

/** Mock portal that can run standalone or inside a shared shell. */
export default function RoleScreens({
  role = "student",
  section: controlledSection,
  onSectionChange,
  hideNavigation = false,
}: RoleScreensProps) {
  const [internalSection, setInternalSection] =
    useState<RoleSection>("overview");
  const nav = role === "student" ? studentNav : lecturerNav;
  const requestedSection = controlledSection ?? internalSection;
  const section = nav.some((item) => item.id === requestedSection)
    ? requestedSection
    : "overview";
  const [registered, setRegistered] = useState([
    "CS 301",
    "CS 315",
    "MATH 220",
    "CS 340",
  ]);
  const [query, setQuery] = useState("");
  const [profile, setProfile] = useState({
    name: "Alex Morgan",
    email: "alex.morgan@northfield.edu",
    phone: "+1 (555) 014-9283",
  });
  const [draftProfile, setDraftProfile] = useState(profile);
  const [editing, setEditing] = useState(false);
  const [submissions, setSubmissions] = useState(initialSubmissions);
  const [submissionFilter, setSubmissionFilter] = useState("All");
  const [resultCourse, setResultCourse] = useState("CS 301");
  const [scores, setScores] = useState<Record<string, string>>({
    "CS 301:Olivia Bennett": "88",
    "CS 301:Noah Williams": "",
    "CS 301:Liam Carter": "",
  });
  const [notice, setNotice] = useState("");
  const [expandedCourse, setExpandedCourse] = useState<string | null>(null);
  const [term, setTerm] = useState("Fall 2024");

  useEffect(() => {
    setInternalSection("overview");
  }, [role]);
  useEffect(() => {
    setQuery("");
    setExpandedCourse(null);
    setNotice("");
  }, [section, role]);
  useEffect(() => {
    if (!notice) return;
    const timer = window.setTimeout(() => setNotice(""), 3500);
    return () => window.clearTimeout(timer);
  }, [notice]);

  const myCourses = courses.filter((course) =>
    registered.includes(course.code),
  );
  const teachingCourses = courses.filter(
    (course) => course.faculty === "Dr. Eleanor Hayes",
  );
  const pending = submissions.filter(
    (submission) => submission.status === "Pending",
  ).length;
  const navigate = (id: RoleSection) => {
    if (controlledSection === undefined) setInternalSection(id);
    onSectionChange?.(id);
    setQuery("");
    setExpandedCourse(null);
  };
  const toggleRegistration = (code: string) => {
    const isRegistered = registered.includes(code);
    setRegistered((current) =>
      isRegistered
        ? current.filter((item) => item !== code)
        : [...current, code],
    );
    setNotice(
      isRegistered
        ? `${code} was removed from your courses.`
        : `You're registered for ${code}.`,
    );
  };
  const saveProfile = (event: FormEvent) => {
    event.preventDefault();
    setProfile(draftProfile);
    setEditing(false);
    setNotice("Your profile has been updated.");
  };
  const resultsStudents =
    resultCourse === "CS 301"
      ? ["Olivia Bennett", "Noah Williams", "Liam Carter"]
      : ["Ava Robinson", "Olivia Bennett", "Noah Williams"];
  const saveResults = (event: FormEvent) => {
    event.preventDefault();
    const filled = resultsStudents.filter((name) =>
      scores[`${resultCourse}:${name}`]?.trim(),
    );
    if (!filled.length) {
      setNotice("Enter at least one score before saving.");
      return;
    }
    if (
      filled.some((name) => {
        const score = Number(scores[`${resultCourse}:${name}`]);
        return !Number.isFinite(score) || score < 0 || score > 100;
      })
    ) {
      setNotice("Scores must be between 0 and 100.");
      return;
    }
    setNotice(
      `Results saved for ${filled.length} ${filled.length === 1 ? "student" : "students"} in ${resultCourse}.`,
    );
  };
  const visibleSubmissions = submissions.filter(
    (item) =>
      (submissionFilter === "All" || item.status === submissionFilter) &&
      `${item.name} ${item.course} ${item.assignment}`
        .toLowerCase()
        .includes(query.toLowerCase()),
  );
  const courseList = (items: Course[], registration = false) => {
    const filtered = items.filter((course) =>
      `${course.code} ${course.title} ${course.faculty}`
        .toLowerCase()
        .includes(query.toLowerCase()),
    );
    return filtered.length ? (
      <div className="grid gap-4 md:grid-cols-2">
        {filtered.map((course) => (
          <div key={course.code} className={`${panel} p-5`}>
            <div className="flex items-start justify-between gap-3">
              <CourseIcon course={course} />
              <Badge green={registered.includes(course.code)}>
                {registration
                  ? registered.includes(course.code)
                    ? "Registered"
                    : "Available"
                  : "In progress"}
              </Badge>
            </div>
            <p className="mt-5 text-xs font-semibold tracking-wide text-[#91a097]">
              {course.code} · {course.credits} CREDITS
            </p>
            <h3 className="mt-1.5 text-base font-semibold text-[#263d2f]">
              {course.title}
            </h3>
            <p className="mt-1 text-sm text-[#8d9a90]">{course.faculty}</p>
            <div className="mt-5 flex items-center gap-2 text-xs text-[#89978c]">
              <CalendarDays size={14} />
              {course.schedule}
            </div>
            {!registration && (
              <div className="mt-5">
                <div className="mb-2 flex justify-between text-xs text-[#8a988d]">
                  <span>Course progress</span>
                  <span>{course.progress}%</span>
                </div>
                <div className="h-1.5 rounded-full bg-[#edf1ed]">
                  <div
                    className="h-full rounded-full bg-[#83aa8c]"
                    style={{ width: `${course.progress}%` }}
                  />
                </div>
              </div>
            )}
            <div className="mt-5 border-t border-[#eff2ef] pt-4">
              {registration ? (
                <button
                  type="button"
                  onClick={() => toggleRegistration(course.code)}
                  className={
                    registered.includes(course.code)
                      ? secondaryButton
                      : primaryButton
                  }
                >
                  {registered.includes(course.code)
                    ? "Remove course"
                    : "Register course"}
                </button>
              ) : (
                <>
                  <button
                    type="button"
                    onClick={() =>
                      setExpandedCourse(
                        expandedCourse === course.code ? null : course.code,
                      )
                    }
                    className="inline-flex items-center gap-1.5 text-sm font-semibold text-[#538365] hover:text-[#315f4c]"
                  >
                    {expandedCourse === course.code
                      ? "Hide details"
                      : "View details"}{" "}
                    <ArrowRight size={14} />
                  </button>
                  {expandedCourse === course.code && (
                    <p className="mt-3 text-xs leading-5 text-[#7d8d81]">
                      {course.credits} credits · {course.schedule}. Your course
                      materials and activities are available in this mock
                      portal.
                    </p>
                  )}
                </>
              )}
            </div>
          </div>
        ))}
      </div>
    ) : (
      <Empty text="No courses found. Try a different search." />
    );
  };
  const searchBox = (placeholder: string) => (
    <label className="relative block w-full sm:w-64">
      <Search size={16} className="absolute left-3 top-3 text-[#a4afa6]" />
      <span className="sr-only">{placeholder}</span>
      <input
        value={query}
        onChange={(event) => setQuery(event.target.value)}
        placeholder={placeholder}
        className={`${inputClass} pl-9`}
      />
    </label>
  );

  return (
    <div
      className={`${hideNavigation ? "" : "min-h-screen"} bg-[#f7f9f6] font-sans text-[#263b2e]`}
    >
      <div
        className={`mx-auto flex max-w-[1600px] flex-col lg:flex-row ${hideNavigation ? "" : "min-h-screen"}`}
      >
        {!hideNavigation && (
          <aside className="border-b border-[#e9eee9] bg-white lg:sticky lg:top-0 lg:flex lg:h-screen lg:w-[235px] lg:shrink-0 lg:flex-col lg:border-b-0 lg:border-r">
            <div className="flex items-center gap-3 px-6 py-6">
              <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-[#315f4c] text-white">
                <GraduationCap size={22} />
              </div>
              <div>
                <div className="text-[15px] font-bold tracking-tight text-[#294435]">
                  Northfield
                </div>
                <div className="text-[10px] font-semibold uppercase tracking-[0.17em] text-[#a3aea5]">
                  University
                </div>
              </div>
            </div>
            <div className="px-5 pb-2 pt-3 text-[10px] font-bold uppercase tracking-[0.15em] text-[#aab5ac]">
              Main menu
            </div>
            <nav
              aria-label={`${role} navigation`}
              className="flex gap-1 overflow-x-auto px-3 pb-4 lg:flex-col lg:overflow-visible"
            >
              {nav.map(({ id, label, icon: Icon }) => (
                <button
                  type="button"
                  key={id}
                  onClick={() => navigate(id)}
                  aria-current={section === id ? "page" : undefined}
                  className={`flex shrink-0 items-center gap-3 rounded-lg px-3 py-3 text-left text-[13px] font-medium transition ${section === id ? "bg-[#eaf3eb] text-[#315f4c]" : "text-[#87958a] hover:bg-[#f6f9f6] hover:text-[#315f4c]"}`}
                >
                  <Icon size={17} strokeWidth={1.8} />
                  {label}
                </button>
              ))}
            </nav>
            <div className="mt-auto hidden px-5 pb-6 lg:block">
              <div className="rounded-xl bg-[#f3f7f1] p-4">
                <Sparkles size={17} className="text-[#84a78a]" />
                <p className="mt-3 text-xs font-semibold text-[#49634e]">
                  A little progress each day
                </p>
                <p className="mt-1 text-xs leading-5 text-[#91a094]">
                  {" "}
                  adds up to something great.
                </p>
              </div>
            </div>
          </aside>
        )}
        <div className="min-w-0 flex-1">
          {!hideNavigation && (
            <header className="flex h-[76px] items-center justify-between border-b border-[#e9eee9] bg-white/80 px-5 sm:px-8 lg:px-10">
              <div className="text-xs text-[#a0aca2]">
                <span>Portal</span>
                <span className="mx-2">/</span>
                <span className="font-medium capitalize text-[#607567]">
                  {nav.find((item) => item.id === section)?.label}
                </span>
              </div>
              <div className="flex items-center gap-3">
                <div className="flex h-9 w-9 items-center justify-center rounded-full bg-[#e5eee4] text-xs font-bold text-[#557b61]">
                  {role === "student"
                    ? profile.name
                        .split(" ")
                        .map((part) => part[0])
                        .join("")
                        .slice(0, 2)
                        .toUpperCase()
                    : "EH"}
                </div>
                <div className="hidden sm:block">
                  <div className="text-xs font-semibold text-[#344c3b]">
                    {role === "student" ? profile.name : "Dr. Eleanor Hayes"}
                  </div>
                  <div className="mt-0.5 text-[11px] capitalize text-[#a3aea5]">
                    {role}
                  </div>
                </div>
                <ChevronDown
                  size={14}
                  className="hidden text-[#a6b1a8] sm:block"
                />
              </div>
            </header>
          )}
          <main className="mx-auto max-w-[1250px] px-5 py-8 sm:px-8 lg:px-10 lg:py-10">
            {section === "overview" && (
              <>
                <SectionHeading
                  eyebrow="Your learning space"
                  title={
                    role === "student"
                      ? `Good morning, ${profile.name.split(" ")[0]}!`
                      : "Good morning, Dr. Hayes!"
                  }
                  description={
                    role === "student"
                      ? "Here's what's happening with your studies today."
                      : "Here's what's happening across your courses today."
                  }
                />
                <div className="mb-6 flex flex-col justify-between gap-5 overflow-hidden rounded-2xl bg-[#e9f1e8] p-6 sm:flex-row sm:items-center sm:p-7">
                  <div>
                    <div className="mb-2 flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.12em] text-[#79967e]">
                      <Sparkles size={14} /> Spring semester 2025
                    </div>
                    <h2 className="text-xl font-semibold tracking-tight text-[#31523c]">
                      {role === "student"
                        ? "You're doing great. Keep it up!"
                        : "Make every lesson count."}
                    </h2>
                    <p className="mt-2 max-w-lg text-sm leading-6 text-[#829887]">
                      {role === "student"
                        ? "Your learning journey is in full swing. Keep track of your courses and celebrate your progress."
                        : "Your teaching workspace, all in one place. Review your courses, submissions, and results."}
                    </p>
                    <button
                      type="button"
                      onClick={() => navigate("courses")}
                      className="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-[#4d8060] hover:text-[#315f4c]"
                    >
                      Explore my courses <ArrowRight size={15} />
                    </button>
                  </div>
                  <div className="hidden h-24 w-24 shrink-0 items-center justify-center rounded-full border border-white/70 bg-white/40 text-[#89ad8c] sm:flex">
                    <BookOpenCheck size={43} strokeWidth={1.2} />
                  </div>
                </div>
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                  {role === "student" ? (
                    <>
                      <Stat
                        icon={<BookOpen size={17} />}
                        label="Enrolled courses"
                        value={myCourses.length}
                        note="This semester"
                      />
                      <Stat
                        icon={<GraduationCap size={17} />}
                        label="Current GPA"
                        value="4.26"
                        note="Out of 5.00"
                      />
                      <Stat
                        icon={<ClipboardCheck size={17} />}
                        label="Registered credits"
                        value={myCourses.reduce(
                          (sum, course) => sum + course.credits,
                          0,
                        )}
                        note="Spring 2025"
                      />
                      <Stat
                        icon={<CalendarDays size={17} />}
                        label="Academic standing"
                        value="Good"
                        note="Keep up the great work"
                      />
                    </>
                  ) : (
                    <>
                      <Stat
                        icon={<BookOpen size={17} />}
                        label="Teaching courses"
                        value={teachingCourses.length}
                        note="This semester"
                      />
                      <Stat
                        icon={<Users size={17} />}
                        label="Total students"
                        value="86"
                        note="Across your courses"
                      />
                      <Stat
                        icon={<FileText size={17} />}
                        label="Pending reviews"
                        value={pending}
                        note="Submissions to review"
                      />
                      <Stat
                        icon={<CalendarDays size={17} />}
                        label="Current term"
                        value="Spring"
                        note="Academic year 2024–25"
                      />
                    </>
                  )}
                </div>
                <div className="mt-6 grid gap-5 xl:grid-cols-[1.5fr_1fr]">
                  <div className={`${panel} p-6`}>
                    <CardTitle
                      title={role === "student" ? "My courses" : "Your courses"}
                      subtitle="A quick look at this semester"
                      action={
                        <button
                          type="button"
                          onClick={() => navigate("courses")}
                          className="text-xs font-semibold text-[#679473] hover:text-[#315f4c]"
                        >
                          View all →
                        </button>
                      }
                    />
                    <div className="mt-4 divide-y divide-[#f0f3f0]">
                      {(role === "student" ? myCourses : teachingCourses)
                        .slice(0, 3)
                        .map((course) => (
                          <div
                            key={course.code}
                            className="flex items-center gap-3 py-4"
                          >
                            <CourseIcon course={course} />
                            <div className="min-w-0 flex-1">
                              <p className="truncate text-sm font-semibold text-[#3a5040]">
                                {course.title}
                              </p>
                              <p className="mt-1 text-xs text-[#9aa79d]">
                                {course.code} · {course.credits} credits
                              </p>
                            </div>
                            <span className="hidden text-xs text-[#8f9e92] sm:block">
                              {course.schedule}
                            </span>
                          </div>
                        ))}
                      {role === "student" && !myCourses.length && (
                        <Empty text="You haven't registered for any courses yet." />
                      )}
                    </div>
                  </div>
                  <div className={`${panel} p-6`}>
                    <CardTitle
                      title="At a glance"
                      subtitle="Spring semester 2025"
                    />
                    <div className="mt-5 space-y-5">
                      <div className="flex gap-3">
                        <div className="rounded-lg bg-[#f3f0e4] p-2.5 text-[#b49d65]">
                          <CalendarDays size={17} />
                        </div>
                        <div>
                          <p className="text-sm font-semibold text-[#43594a]">
                            Semester in progress
                          </p>
                          <p className="mt-1 text-xs text-[#99a69c]">
                            Spring 2025 · Academic year 2024–25
                          </p>
                        </div>
                      </div>
                      <div className="flex gap-3">
                        <div className="rounded-lg bg-[#e9f2eb] p-2.5 text-[#77a182]">
                          <CheckCircle2 size={17} />
                        </div>
                        <div>
                          <p className="text-sm font-semibold text-[#43594a]">
                            {role === "student"
                              ? "Your academic record"
                              : "Review submissions"}
                          </p>
                          <p className="mt-1 text-xs text-[#99a69c]">
                            {role === "student"
                              ? "Your latest results are ready to view."
                              : `${pending} submissions are waiting for review.`}
                          </p>
                          <button
                            type="button"
                            onClick={() =>
                              navigate(
                                role === "student" ? "results" : "submissions",
                              )
                            }
                            className="mt-2 text-xs font-semibold text-[#679473] hover:text-[#315f4c]"
                          >
                            Take a look →
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </>
            )}
            {section === "profile" && role === "student" && (
              <>
                <SectionHeading
                  eyebrow="Student account"
                  title="My profile"
                  description="View and manage your personal information."
                />
                <div className={`${panel} max-w-4xl overflow-hidden`}>
                  <div className="h-24 bg-[#eaf2e9]" />
                  <div className="px-6 pb-7 sm:px-8">
                    <div className="-mt-10 flex flex-wrap items-end justify-between gap-4">
                      <div className="flex items-end gap-4">
                        <div className="flex h-20 w-20 items-center justify-center rounded-2xl border-4 border-white bg-[#d4e4d4] text-xl font-semibold text-[#587e60]">
                          {profile.name
                            .split(" ")
                            .map((part) => part[0])
                            .join("")
                            .slice(0, 2)
                            .toUpperCase()}
                        </div>
                        <div className="pb-1">
                          <h2 className="text-lg font-semibold">
                            {profile.name}
                          </h2>
                          <p className="mt-1 text-xs text-[#95a399]">
                            Student · STU-2024-018
                          </p>
                        </div>
                      </div>
                      {!editing && (
                        <button
                          type="button"
                          className={secondaryButton}
                          onClick={() => {
                            setDraftProfile(profile);
                            setEditing(true);
                          }}
                        >
                          Edit profile
                        </button>
                      )}
                    </div>
                    <form onSubmit={saveProfile} className="mt-8">
                      <CardTitle
                        title="Personal information"
                        subtitle="Your details and university contact information"
                      />
                      <div className="mt-5 grid gap-5 sm:grid-cols-2">
                        {(
                          [
                            { key: "name", label: "Full name", type: "text" },
                            {
                              key: "email",
                              label: "Email address",
                              type: "email",
                            },
                            {
                              key: "phone",
                              label: "Phone number",
                              type: "tel",
                            },
                          ] as const
                        ).map(({ key, label, type }) => (
                          <label
                            key={key}
                            className="text-xs font-semibold text-[#7d8e81]"
                          >
                            {label}
                            {editing ? (
                              <input
                                required
                                type={type}
                                value={draftProfile[key]}
                                onChange={(event) =>
                                  setDraftProfile({
                                    ...draftProfile,
                                    [key]: event.target.value,
                                  })
                                }
                                className={`${inputClass} mt-2`}
                              />
                            ) : (
                              <span className="mt-2 block rounded-lg bg-[#f8faf8] px-3.5 py-3 text-sm font-medium text-[#43594a]">
                                {profile[key]}
                              </span>
                            )}
                          </label>
                        ))}
                        <div className="text-xs font-semibold text-[#7d8e81]">
                          Programme
                          <span className="mt-2 block rounded-lg bg-[#f8faf8] px-3.5 py-3 text-sm font-medium text-[#43594a]">
                            B.Sc. Computer Science
                          </span>
                        </div>
                      </div>
                      {editing && (
                        <div className="mt-6 flex gap-2">
                          <button className={primaryButton} type="submit">
                            Save changes
                          </button>
                          <button
                            className={secondaryButton}
                            type="button"
                            onClick={() => setEditing(false)}
                          >
                            Cancel
                          </button>
                        </div>
                      )}
                    </form>
                  </div>
                </div>
              </>
            )}
            {(section === "courses" ||
              (section === "registration" && role === "student")) && (
              <>
                <SectionHeading
                  eyebrow={
                    role === "student" ? "Your academics" : "Teaching workspace"
                  }
                  title={
                    section === "registration"
                      ? "Course registration"
                      : "My courses"
                  }
                  description={
                    section === "registration"
                      ? "Explore available courses and build your semester."
                      : role === "student"
                        ? "All your enrolled courses, in one place."
                        : "Manage the courses you're teaching this semester."
                  }
                  action={searchBox("Search courses...")}
                />
                {section === "registration" && (
                  <div className="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[#e0ece1] bg-[#edf5ed] px-5 py-4 text-sm text-[#60816a]">
                    <span>
                      <strong className="text-[#436c50]">Spring 2025</strong>{" "}
                      registration is open · {myCourses.length} courses selected
                    </span>
                    <span className="text-xs">
                      {myCourses.reduce(
                        (sum, course) => sum + course.credits,
                        0,
                      )}{" "}
                      registered credits
                    </span>
                  </div>
                )}
                {courseList(
                  section === "registration"
                    ? courses
                    : role === "student"
                      ? myCourses
                      : teachingCourses,
                  section === "registration",
                )}
              </>
            )}
            {section === "results" && role === "student" && (
              <>
                <SectionHeading
                  eyebrow="Your academics"
                  title="Results"
                  description="A closer look at your academic performance."
                />
                <div className="mb-5 grid gap-4 sm:grid-cols-3">
                  <Stat
                    icon={<GraduationCap size={17} />}
                    label="Cumulative GPA"
                    value="4.26"
                    note="Out of 5.00"
                  />
                  <Stat
                    icon={<BookOpen size={17} />}
                    label="Credits completed"
                    value="52"
                    note="Across all completed terms"
                  />
                  <Stat
                    icon={<CheckCircle2 size={17} />}
                    label="Academic standing"
                    value="Good"
                    note="You're on track"
                  />
                </div>
                <div className={`${panel} overflow-hidden`}>
                  <div className="flex flex-wrap items-center justify-between gap-3 p-6">
                    <CardTitle
                      title="Semester results"
                      subtitle="Your most recently completed semester"
                    />
                    <Badge green>Fall 2024</Badge>
                  </div>
                  <div className="overflow-x-auto">
                    <table className="w-full min-w-[600px] text-left text-sm">
                      <thead className="bg-[#f8faf8] text-[11px] uppercase tracking-wider text-[#a0ada3]">
                        <tr>
                          <th className="px-6 py-3 font-semibold">Course</th>
                          <th className="px-6 py-3 font-semibold">Credits</th>
                          <th className="px-6 py-3 font-semibold">Score</th>
                          <th className="px-6 py-3 font-semibold">
                            Grade point / 5.0
                          </th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-[#eff2ef]">
                        {pastResults.map((result) => (
                          <tr key={result.code}>
                            <td className="px-6 py-4">
                              <div className="font-semibold text-[#415649]">
                                {result.course}
                              </div>
                              <div className="mt-1 text-xs text-[#9aa79d]">
                                {result.code}
                              </div>
                            </td>
                            <td className="px-6 py-4 text-[#7f8f83]">
                              {result.credits}
                            </td>
                            <td className="px-6 py-4 text-[#7f8f83]">
                              {result.score}%
                            </td>
                            <td className="px-6 py-4">
                              <Badge green>{result.grade}</Badge>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </div>
              </>
            )}
            {section === "history" && role === "student" && (
              <>
                <SectionHeading
                  eyebrow="Your academics"
                  title="Academic history"
                  description="A record of your progress, semester by semester."
                />
                <div className={`${panel} max-w-4xl p-6`}>
                  <CardTitle
                    title="Your academic journey"
                    subtitle="Select a semester to see your record"
                  />
                  <div className="mt-5 space-y-3">
                    {[
                      {
                        name: "Fall 2024",
                        gpa: "4.31",
                        credits: 13,
                        detail: "4 courses completed",
                      },
                      {
                        name: "Spring 2024",
                        gpa: "4.21",
                        credits: 15,
                        detail: "5 courses completed",
                      },
                      {
                        name: "Fall 2023",
                        gpa: "4.17",
                        credits: 12,
                        detail: "4 courses completed",
                      },
                    ].map((item) => (
                      <div
                        key={item.name}
                        className="overflow-hidden rounded-xl border border-[#edf1ed]"
                      >
                        <button
                          type="button"
                          aria-expanded={term === item.name}
                          onClick={() =>
                            setTerm(term === item.name ? "" : item.name)
                          }
                          className="flex w-full items-center justify-between gap-3 p-4 text-left hover:bg-[#fafcfa]"
                        >
                          <div className="flex items-center gap-3">
                            <div className="rounded-lg bg-[#edf5ee] p-2.5 text-[#779a7d]">
                              <CalendarDays size={17} />
                            </div>
                            <div>
                              <p className="text-sm font-semibold text-[#405647]">
                                {item.name}
                              </p>
                              <p className="mt-1 text-xs text-[#9aa79d]">
                                {item.detail}
                              </p>
                            </div>
                          </div>
                          <div className="flex items-center gap-4">
                            <Badge green>Completed</Badge>
                            <ChevronDown
                              size={16}
                              className={`text-[#a0aea3] transition ${term === item.name ? "rotate-180" : ""}`}
                            />
                          </div>
                        </button>
                        {term === item.name && (
                          <div className="grid grid-cols-2 gap-3 border-t border-[#eff2ef] bg-[#fafcfa] p-4 text-sm">
                            <div>
                              <span className="block text-xs text-[#9aa79d]">
                                Semester GPA
                              </span>
                              <strong className="mt-1 block text-[#496050]">
                                {item.gpa}
                              </strong>
                            </div>
                            <div>
                              <span className="block text-xs text-[#9aa79d]">
                                Credits earned
                              </span>
                              <strong className="mt-1 block text-[#496050]">
                                {item.credits}
                              </strong>
                            </div>
                            {item.name === "Fall 2024" && (
                              <button
                                type="button"
                                onClick={() => navigate("results")}
                                className="col-span-2 mt-1 text-left text-xs font-semibold text-[#679473]"
                              >
                                View semester results →
                              </button>
                            )}
                          </div>
                        )}
                      </div>
                    ))}
                  </div>
                </div>
              </>
            )}
            {section === "results" && role === "lecturer" && (
              <>
                <SectionHeading
                  eyebrow="Teaching workspace"
                  title="Result entry"
                  description="Enter and save student scores for your courses."
                />
                <form
                  onSubmit={saveResults}
                  className={`${panel} overflow-hidden`}
                >
                  <div className="flex flex-wrap items-end justify-between gap-4 p-6">
                    <CardTitle
                      title="Student results"
                      subtitle="Scores out of 100 · mock grade points: 70+ = 5, 60+ = 4, 50+ = 3, 45+ = 2, 40+ = 1, below 40 = 0"
                    />
                    <label className="text-xs font-semibold text-[#7d8e81]">
                      Course
                      <select
                        value={resultCourse}
                        onChange={(event) =>
                          setResultCourse(event.target.value)
                        }
                        className={`${inputClass} mt-2 min-w-[220px]`}
                      >
                        {teachingCourses.map((course) => (
                          <option key={course.code} value={course.code}>
                            {course.code} · {course.title}
                          </option>
                        ))}
                      </select>
                    </label>
                  </div>
                  <div className="overflow-x-auto">
                    <table className="w-full min-w-[500px] text-left text-sm">
                      <thead className="bg-[#f8faf8] text-[11px] uppercase tracking-wider text-[#a0ada3]">
                        <tr>
                          <th className="px-6 py-3 font-semibold">Student</th>
                          <th className="px-6 py-3 font-semibold">
                            Score / 100
                          </th>
                          <th className="px-6 py-3 font-semibold">
                            Grade point / 5.0
                          </th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-[#eff2ef]">
                        {resultsStudents.map((name, index) => {
                          const value = scores[`${resultCourse}:${name}`] ?? "";
                          const n = Number(value);
                          const grade =
                            value === "" ||
                            !Number.isFinite(n) ||
                            n < 0 ||
                            n > 100
                              ? "—"
                              : gradePoint(n);
                          return (
                            <tr key={name}>
                              <td className="px-6 py-4">
                                <p className="font-semibold text-[#415649]">
                                  {name}
                                </p>
                                <p className="mt-1 text-xs text-[#9aa79d]">
                                  Student {String(index + 1).padStart(3, "0")}
                                </p>
                              </td>
                              <td className="px-6 py-4">
                                <label>
                                  <span className="sr-only">
                                    Score for {name}
                                  </span>
                                  <input
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="1"
                                    value={value}
                                    onChange={(event) =>
                                      setScores({
                                        ...scores,
                                        [`${resultCourse}:${name}`]:
                                          event.target.value,
                                      })
                                    }
                                    placeholder="—"
                                    className={`${inputClass} max-w-28`}
                                  />
                                </label>
                              </td>
                              <td className="px-6 py-4">
                                <Badge green>{grade}</Badge>
                              </td>
                            </tr>
                          );
                        })}
                      </tbody>
                    </table>
                  </div>
                  <div className="border-t border-[#eff2ef] p-6">
                    <button type="submit" className={primaryButton}>
                      <Check size={15} /> Save results
                    </button>
                  </div>
                </form>
              </>
            )}
            {section === "submissions" && role === "lecturer" && (
              <>
                <SectionHeading
                  eyebrow="Teaching workspace"
                  title="Submissions"
                  description="Keep track of student work and reviews."
                  action={searchBox("Search submissions...")}
                />
                <div className={`${panel} overflow-hidden`}>
                  <div className="flex flex-wrap items-center justify-between gap-4 p-6">
                    <CardTitle
                      title="Student submissions"
                      subtitle={`${pending} awaiting review`}
                    />
                    <div
                      className="flex rounded-lg bg-[#f4f7f4] p-1"
                      aria-label="Filter submissions"
                    >
                      {["All", "Pending", "Reviewed"].map((filter) => (
                        <button
                          type="button"
                          key={filter}
                          onClick={() => setSubmissionFilter(filter)}
                          aria-pressed={submissionFilter === filter}
                          className={`rounded-md px-3 py-1.5 text-xs font-semibold ${submissionFilter === filter ? "bg-white text-[#4a7457] shadow-sm" : "text-[#95a399]"}`}
                        >
                          {filter}
                        </button>
                      ))}
                    </div>
                  </div>
                  <div className="overflow-x-auto">
                    <table className="w-full min-w-[760px] text-left text-sm">
                      <thead className="bg-[#f8faf8] text-[11px] uppercase tracking-wider text-[#a0ada3]">
                        <tr>
                          <th className="px-6 py-3 font-semibold">Student</th>
                          <th className="px-6 py-3 font-semibold">
                            Assignment
                          </th>
                          <th className="px-6 py-3 font-semibold">Submitted</th>
                          <th className="px-6 py-3 font-semibold">Status</th>
                          <th className="px-6 py-3 font-semibold">Action</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-[#eff2ef]">
                        {visibleSubmissions.map((item) => (
                          <tr key={item.id}>
                            <td className="px-6 py-4">
                              <p className="font-semibold text-[#415649]">
                                {item.name}
                              </p>
                              <p className="mt-1 text-xs text-[#9aa79d]">
                                {item.studentId}
                              </p>
                            </td>
                            <td className="px-6 py-4">
                              <p className="text-[#617668]">
                                {item.assignment}
                              </p>
                              <p className="mt-1 text-xs text-[#9aa79d]">
                                {item.course}
                              </p>
                            </td>
                            <td className="px-6 py-4 text-[#8c9b8f]">
                              {item.submitted}
                            </td>
                            <td className="px-6 py-4">
                              <Badge green={item.status === "Reviewed"}>
                                {item.status}
                              </Badge>
                            </td>
                            <td className="px-6 py-4">
                              <button
                                type="button"
                                onClick={() => {
                                  setSubmissions((current) =>
                                    current.map((submission) =>
                                      submission.id === item.id
                                        ? {
                                            ...submission,
                                            status:
                                              submission.status === "Pending"
                                                ? "Reviewed"
                                                : "Pending",
                                          }
                                        : submission,
                                    ),
                                  );
                                  setNotice(
                                    item.status === "Pending"
                                      ? "Submission marked as reviewed."
                                      : "Submission moved back to pending.",
                                  );
                                }}
                                className="text-xs font-semibold text-[#679473] hover:text-[#315f4c]"
                              >
                                {item.status === "Pending"
                                  ? "Mark reviewed"
                                  : "Undo review"}
                              </button>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                    {!visibleSubmissions.length && (
                      <Empty text="No submissions match your filters." />
                    )}
                  </div>
                </div>
              </>
            )}
          </main>
          {!hideNavigation && (
            <footer className="mx-auto max-w-[1250px] px-5 pb-7 text-xs text-[#b0bbb2] sm:px-8 lg:px-10">
              © 2025 Northfield University <span className="mx-2">·</span> A
              better place to learn
            </footer>
          )}
        </div>
      </div>
      {notice && (
        <div
          role="status"
          className="fixed bottom-5 right-5 z-50 flex max-w-[calc(100vw-2.5rem)] items-center gap-3 rounded-xl bg-[#294c38] px-4 py-3 text-sm text-white shadow-lg"
        >
          <CheckCircle2 size={17} className="shrink-0" />
          <span>{notice}</span>
          <button
            type="button"
            aria-label="Dismiss notification"
            onClick={() => setNotice("")}
            className="ml-2 text-white/70 hover:text-white"
          >
            <X size={16} />
          </button>
        </div>
      )}
    </div>
  );
}
