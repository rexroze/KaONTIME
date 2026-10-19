import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api } from '../boot/api'

export interface User { id:number; name:string; email:string; isAdmin:boolean }
export const useSessionStore=defineStore('session',()=>{
 const user=ref<User|null>(null),token=ref(sessionStorage.getItem('kaontime-token')||''),favorites=ref<number[]>([]),ready=ref(false)
 const favoriteIds=computed(()=>favorites.value)
 const isAdmin=computed(()=>!!user.value?.isAdmin)
 async function restore(){if(ready.value)return;ready.value=true;token.value=sessionStorage.getItem('kaontime-token')||'';if(!token.value)return;try{const{data}=await api.get('/auth/me');user.value=data.data}catch{clear()}}
 function clear(){token.value='';user.value=null;favorites.value=[];sessionStorage.removeItem('kaontime-token')}
 async function authenticate(endpoint:string,payload:Record<string,string>){const{data}=await api.post(endpoint,payload);user.value=data.data.user;token.value=data.data.token;sessionStorage.setItem('kaontime-token',token.value);await loadFavorites()}
 async function logout(){try{await api.post('/auth/logout')}finally{clear()}}
 async function loadFavorites(){if(!user.value)return;const{data}=await api.get('/favorites');favorites.value=(data.data||[]).map((f:{id:number})=>f.id)}
 return{user,token,favorites,favoriteIds,isAdmin,restore,authenticate,logout,clear,loadFavorites}
})
