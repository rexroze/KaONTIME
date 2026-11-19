import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api } from '../boot/api'
import { clearToken, getToken, initTokenStore, setToken } from '../boot/token-store'

export interface User { id:number; name:string; email:string; isAdmin:boolean }
export const useSessionStore=defineStore('session',()=>{
 const user=ref<User|null>(null),token=ref(getToken()),favorites=ref<number[]>([]),ready=ref(false)
 const favoriteIds=computed(()=>favorites.value)
 const isAdmin=computed(()=>!!user.value?.isAdmin)
 async function restore(){if(ready.value)return;ready.value=true;await initTokenStore();token.value=getToken();if(!token.value)return;try{const{data}=await api.get('/auth/me');user.value=data.data}catch{clear()}}
 function clear(){token.value='';user.value=null;favorites.value=[];void clearToken()}
 async function authenticate(endpoint:string,payload:Record<string,string>){const{data}=await api.post(endpoint,payload);user.value=data.data.user;token.value=data.data.token;await setToken(token.value);await loadFavorites()}
 async function logout(){try{await api.post('/auth/logout')}finally{clear()}}
 async function loadFavorites(){if(!user.value)return;const{data}=await api.get('/favorites');favorites.value=(data.data||[]).map((f:{id:number})=>f.id)}
 return{user,token,favorites,favoriteIds,isAdmin,restore,authenticate,logout,clear,loadFavorites}
})
