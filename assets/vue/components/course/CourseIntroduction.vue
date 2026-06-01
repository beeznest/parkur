<script setup>
import { useI18n } from "vue-i18n"
import { computed, ref } from "vue"
import { useRouter } from "vue-router"
import { storeToRefs } from "pinia"
import EmptyState from "../EmptyState.vue"
import BaseButton from "../basecomponents/BaseButton.vue"
import Skeleton from "primevue/skeleton"
import { useCidReqStore } from "../../store/cidReq"
import { usePlatformConfig } from "../../store/platformConfig"
import cToolIntroService from "../../services/cToolIntroService"
import courseService from "../../services/courseService"
import { filterTranslatedHtml } from "../../../js/translatehtml.js"

const { t } = useI18n()
const router = useRouter()

const cidReqStore = useCidReqStore()

const { course, session } = storeToRefs(cidReqStore)

const intro = ref(null)
const currentSessionId = session.value?.id
const hasMismatchedSidLinks = computed(() => {
  if (!intro.value?.introText || !currentSessionId) return false

  const regex = /sid=(\d+)/g
  const matches = intro.value.introText.match(regex)
  return matches?.some((match) => match !== `sid=${currentSessionId}`) || false
})

defineProps({
  isAllowedToEdit: {
    type: Boolean,
    required: true,
  },
})

const platformConfigStore = usePlatformConfig()

courseService.loadHomeIntro(course.value.id, session.value?.id).then((data) => (intro.value = data))

const displayedIntroText = computed(() => {
  const text = intro.value?.introText
  if (!text) return null

  if ("true" === platformConfigStore.getSetting("editor.translate_html")) {
    return filterTranslatedHtml(text, window.user?.locale)
  }

  return text
})

async function updateIntroLinks() {
  if (!intro.value?.introText || !currentSessionId) return

  const updatedIntroText = intro.value.introText.replace(/sid=\d+/g, `sid=${currentSessionId}`)

  return {
    ...data,
    c_tool: data.c_tool || {
      iid: data.cToolId || null,
      title: props.tool,
    },
  }
}

function getCourseToolId() {
  return intro.value?.c_tool?.iid || intro.value?.cToolId || null
}

async function loadIntro() {
  if (!isEnabled.value || !course.value?.id) {
    intro.value = null
    return
  }

  isLoading.value = true

  try {
    let data = null

    if (props.tool === "course_homepage") {
      data = await courseService.loadHomeIntro(course.value.id, currentSessionId.value)
    } else {
      data = await cToolIntroService.findCourseHomeInro(course.value.id, {
        sid: currentSessionId.value,
        tool: props.tool,
      })
    }

    intro.value = normalizeIntroResponse(data)
  } catch (error) {
    console.error("Error loading tool introduction:", error)
    intro.value = null
  } finally {
    isLoading.value = false
  }
}

async function createEmptyIntroIfNeeded() {
  if (intro.value?.iid && getCourseToolId()) {
    return
  }

  const response = await cToolIntroService.addToolIntro(course.value.id, {
    tool: props.tool,
    introText: intro.value?.introText || "",
    sid: currentSessionId.value || 0,
    // Course context derived server-side from the gated session course.
    resourceLinkList: [{ visibility: "published" }],
  })

  intro.value = normalizeIntroResponse(response)
}

async function openEditor() {
  await createEmptyIntroIfNeeded()

  const courseToolId = getCourseToolId()

  if (!intro.value?.iid || !courseToolId) {
    console.error("Cannot open tool introduction editor.", intro.value)
    return
  }

  try {
    const response = await cToolIntroService.addToolIntro(course.value.id, payload)

    if (intro.value.iid) {
      alert(t("Introduction updated successfully!"))
    } else {
      intro.value.iid = response.data.iid
      alert(t("Introduction created successfully!"))
    }

    intro.value.introText = updatedIntroText
  } catch (error) {
    console.error("Error updating or creating the introduction:", error)
    alert(t("An error occurred."))
  }
}

const goToIntroCreate = () => {
  router.push({
    name: "ToolIntroCreate",
    params: {
      courseTool: intro.value.c_tool.iid,
    },
    query: {
      cid: course.value.id,
      sid: session.value?.id,
      parentResourceNodeId: course.value.resourceNode.id,
      ctoolIntroId: intro.value.iid,
    },
  })
}

const goToIntroUpdate = () => {
  router.push({
    name: "ToolIntroUpdate",
    params: {
      id: `/api/c_tool_intros/${intro.value.iid}`,
    },
    query: {
      cid: course.value.id,
      sid: session.value?.id,
      ctoolintroIid: intro.value.iid,
      ctoolId: intro.value.c_tool.iid,
      parentResourceNodeId: course.value.resourceNode.id,
      id: `/api/c_tool_intros/${intro.value.iid}`,
    },
  })
}

const goToCreateOrUpdate = () => {
  if (intro.value.createInSession) {
    goToIntroCreate()

    return
  }

  goToIntroUpdate()
}

defineExpose({
  introduction: intro,
  goToCreateOrUpdate,
})
</script>

<template>
  <div
    v-if="intro"
    class="mb-4"
  >
    <div v-if="intro.introText">
      <div v-html="displayedIntroText" />
      <BaseButton
        v-if="isAllowedToEdit && hasMismatchedSidLinks"
        :label="t('Update introduction links')"
        class="mt-2"
        icon="refresh"
        type="primary"
        @click="updateIntroLinks"
      />
    </div>
    <div v-else-if="isAllowedToEdit">
      <EmptyState
        :detail="t('Add a course introduction to display to your students.')"
        :summary="t('You don\'t have any course content yet.')"
        icon="courses"
      >
        <BaseButton
          :label="t('Course introduction')"
          class="mt-4"
          icon="plus"
          type="success"
          @click="goToIntroCreate"
        />
      </EmptyState>
    </div>
  </div>
  <Skeleton
    v-else
    class="mb-4"
    height="21.5rem"
  />
</template>
