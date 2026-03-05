<?php

namespace OwlConcept\SettingsBundle\Controller;

use OwlConcept\SettingsBundle\Service\SettingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class SettingsController extends AbstractController
{
    public function __construct(
        private readonly SettingsService $settingsService,
    ) {
    }

    /**
     * Save global settings for a group (AJAX endpoint).
     */
    #[Route('/owl-settings/save/{groupKey}', name: 'owl_settings_save_group', methods: ['POST'])]
    public function saveGroup(string $groupKey, Request $request): JsonResponse
    {
        try {
            // Check if the group exists
            $groups = $this->settingsService->getGroups();
            $group = $groups[$groupKey] ?? null;

            if ($group === null) {
                return new JsonResponse([
                    'success' => false,
                    'error' => sprintf('Groupe de paramètres "%s" inconnu.', $groupKey),
                ], 404);
            }

            // Check role if defined on the group
            if ($group->getRole() !== null && !$this->isGranted($group->getRole())) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Accès refusé.',
                ], 403);
            }

            // Extract values from request
            $values = [];
            foreach ($group->getSettings() as $setting) {
                $key = $setting->getKey();
                if ($request->request->has($key)) {
                    $values[$key] = $request->request->get($key);
                }
            }

            $this->settingsService->saveGroup($groupKey, $values);

            return new JsonResponse(['success' => true]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Save user preferences (AJAX endpoint).
     */
    #[Route('/owl-settings/user-preferences/save', name: 'owl_settings_save_user_preferences', methods: ['POST'])]
    public function saveUserPreferences(Request $request): JsonResponse
    {
        try {
            $user = $this->getUser();
            if ($user === null) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Utilisateur non connecté.',
                ], 401);
            }

            // Extract values from request
            $definitions = $this->settingsService->getUserPreferenceDefinitions();
            $values = [];

            foreach ($definitions as $definition) {
                $key = $definition->getKey();
                if ($request->request->has($key)) {
                    $values[$key] = $request->request->get($key);
                }
            }

            $this->settingsService->saveAllUserPreferences($values);

            return new JsonResponse(['success' => true]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
