/* ============================================================
   CONFIG.JS  -  EDIT YOUR TEXT AND IMAGES HERE
   Almost all words and pictures on the website come from this
   file, so you rarely need to touch the HTML.
   Images go in the /images folder. Example: "images/logo.png"
   If an image file is missing, a gray placeholder is shown.
   ============================================================ */
const SITE = {
  /* ---------- EDIT: BRANDING ---------- */
  name: "LSPU Lost and Found",          // shown in header and browser tab
  campus: "LSPU-LB",           // EDIT: your campus (this version covers ONE campus)
  logo: "assets/images/logo.jpg",              // EDIT IMAGE: logo (square, about 120x120)

  /* ---------- EDIT: HOME PAGE HERO ---------- */
  hero: {
    image: "assets/images/hero.png",           // EDIT IMAGE: big background photo of your campus
    title: "Lost something on campus? Found something?",
    text: "A central place for students, teachers, and school personnel to report lost belongings, post found items, and search for missing possessions.",
    searchPlaceholder: "Search for an ID, wallet, phone, keys..."
  },

  /* ---------- EDIT: HOW IT WORKS (home page) ---------- */
  steps: [
    { title: "Report or post",  text: "Report what you lost, or post what you found." },
    { title: "Search and match", text: "Browse listings or let the system suggest a match." },
    { title: "Claim",           text: "Answer a few questions only the real owner would know." },
    { title: "Pick up",         text: "Get your item at the office after the admin verifies you." }
  ],

  /* ---------- EDIT: CONTACT / OFFICE (footer + item page) ---------- */
  contact: {
    office: "Student Affairs Office, Main Building",  // EDIT
    hours: "Mon to Fri, 8:00 AM to 5:00 PM",           // EDIT
    email: "lostandfound@lspu.edu.ph",           // EDIT
    phone: "(000) 000-0000"                            // EDIT
  },
  footerText: "Promoting honesty, responsibility, and cooperation in our campus community.",

  /* ---------- EDIT: DROPDOWN CHOICES ---------- */
  categories: ["School ID", "Wallet", "Mobile Phone", "Keys", "Books", "Bag", "Clothing", "Other"],
  locations:  ["Library", "Canteen", "Main Building", "Gymnasium", "Computer Lab", "Parking Area", "Other"],
  statuses:   ["Lost", "Found", "Claimed", "Returned"],

  /* ---------- SAMPLE ITEMS (replace with database data later) ----------
     type: "lost" or "found"   photo: image path (can be "")            */
  items: [
    { id:1, type:"found", title:"Black Leather Wallet", category:"Wallet", location:"Canteen", date:"2026-11-12", status:"Found", photo:"images/item1.jpg", desc:"Black wallet found under a table. Contains some cards. Kept at the office.", kept:"Student Affairs Office" },
    { id:2, type:"lost",  title:"School ID (Blue Lanyard)", category:"School ID", location:"Library", date:"2026-11-14", status:"Lost", photo:"images/item2.jpg", desc:"Lost near the second-floor reading area. Blue lanyard attached.", kept:"" },
    { id:3, type:"found", title:"Set of Keys with Red Keychain", category:"Keys", location:"Gymnasium", date:"2026-11-15", status:"Found", photo:"images/item3.jpg", desc:"Three keys on a red keychain, found near the bleachers.", kept:"Security Office" },
    { id:4, type:"lost",  title:"Samsung Phone (Black Case)", category:"Mobile Phone", location:"Computer Lab", date:"2026-11-16", status:"Lost", photo:"assets/images/item4.png", desc:"Black case with a sticker on the back. Lost after afternoon class.", kept:"" },
    { id:5, type:"found", title:"Calculus Textbook", category:"Books", location:"Main Building", date:"2026-11-17", status:"Claimed", photo:"assets/images/item5.jpg", desc:"Textbook with a name written inside the cover.", kept:"Student Affairs Office" },
    { id:6, type:"found", title:"Gray Backpack", category:"Bag", location:"Parking Area", date:"2026-11-18", status:"Found", photo:"assets/images/item6.jpg", desc:"Gray backpack with notebooks inside.", kept:"Security Office" }
  ],

  /* ---------- SAMPLE DATA for My Posts / My Claims / Admin pages ---------- */
  myPostIds: [2, 4],
  myClaims: [ { item:"Black Leather Wallet", date:"2026-11-16", status:"Pending" } ],
  pendingPosts: [ { id:7, who:"Juan Dela Cruz", title:"Umbrella (Blue)", type:"found", date:"2026-11-19" } ],
  pendingClaims: [ { id:1, who:"Maria Santos", item:"Black Leather Wallet", proof:"Has a photo of my family and a P500 bill.", date:"2026-11-19" } ]
};
