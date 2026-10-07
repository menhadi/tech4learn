import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import type { Rule } from 'postcss';

export default defineConfig({
  plugins:[react()],
  base:'/attendance-ui/',
  define:{'import.meta.env.VITE_API_URL':JSON.stringify('/attendance/api')},
  css:{postcss:{plugins:[{postcssPlugin:'scope-native-attendance',Rule(rule:Rule){
    if(rule.parent?.type==='atrule' && /keyframes$/i.test((rule.parent as {name:string}).name))return;
    rule.selectors=rule.selectors.map(selector=>{
      if(selector.startsWith('#foundation-attendance'))return selector;
      if([':root','body','html'].includes(selector))return '#foundation-attendance';
      for(const prefix of ['body ', 'html ', ':root '])if(selector.startsWith(prefix))return '#foundation-attendance '+selector.slice(prefix.length);
      return '#foundation-attendance '+selector;
    });
  }}]}},
  build:{outDir:'../../platform/public/attendance-ui',emptyOutDir:true,manifest:'manifest.json',
    rollupOptions:{input:'src/foundation-attendance.tsx'}},
});
