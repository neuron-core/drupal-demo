import { createApp } from 'vue'
import '@copilotkit/vue/styles.css'
import App from './App.vue'
import './chat.css'

createApp(App, { agent: window.drupalSettings.neuronDemo.agent }).mount('#neuron-demo')
