// Real application shell with synthetic, read-only transport. No live records.
const organisations = [
  {id:"11111111-1111-4111-8111-111111111111",name:"Community Learning Academy",slug:"synthetic-academy",colour:"#175d50",centre_label:"Centre"},
  {id:"22222222-2222-4222-8222-222222222222",name:"Synthetic Learning Programme",slug:"synthetic-programme",colour:"#9a3412",centre_label:"Centre"},
];
window.fetch = async (input, options = {}) => {
  if(options.method && options.method !== "GET") throw Error("Writes are disabled in this synthetic preview");
  const url = new URL(String(input),location.origin);
  let data: unknown;
  if(url.pathname.endsWith("/auth/me")) data={user:{id:"synthetic-admin",name:"Demo administrator",email:"admin@example.invalid",is_superadmin:false},organisations};
  else if(url.pathname.endsWith("/public/branding")) {
    const org=organisations.find(item=>item.slug===url.searchParams.get("slug"))||organisations[0];
    data={...org,template:"community",welcome:"Synthetic local preview",logo:""};
  } else if(url.pathname.endsWith("/access")) data={access:{roleId:null,roleName:"Organisation administrator",permissions:["organisation.view","centres.view","groups.view","learners.view","exams.manage"],owner:true,scope_type:"organisation",scope_ids:[]},modules:{learners:true,exams:true,attendance:false},catalogue:[]};
  else if(/\/(roles|members|centres|groups)$/.test(url.pathname)) data=[];
  else if(url.pathname.endsWith("/exam-workspace")) data={revision:1,restrictions:["questions","subjects","taking","results"]};
  else throw Error("Unsupported synthetic preview route: "+url.pathname);
  return new Response(JSON.stringify(data),{headers:{"Content-Type":"application/json"}});
};
void import("../../apps/admin/src/main");
