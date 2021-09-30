/**
 * @copyright Copyright (c) 2021 Lyseon Techh <contato@lt.coop.br>
 *
 * @author Lyseon Tech <contato@lt.coop.br>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

import { translate as t } from '@nextcloud/l10n'
import { loadState } from '@nextcloud/initial-state'
import { selectAction } from '../helpers/SelectAction'

const libresignVar = JSON.parse(loadState('libresign', 'config'))

const routes = [
	{
		path: '/apps/libresign/#timeline/sign',
		component: () => import('../views/Timeline/Timeline.vue'),
		name: 'signFiles',
	}, {
		path: '/apps/libresign/#request',
		component: () => import('../views/Request'),
		name: 'requestFiles',
	}, {
		path: '/apps/libresign/sign/:uuid',
		redirect: { name: selectAction(libresignVar.action) },
	}, {
		path: '/apps/libresign/sign/:uuid',
		component: () => import('../views/SignPDF'),
		props: (route) => ({ uuid: route.params.uuid, redirect: false }),
		name: 'SignPDF',
	}, {
		path: '/apps/libresign/sign/:uuid',
		component: () => import('../views/CreateUser'),
		name: 'CreateUser',
		props: (route) => ({
			messageToast: t('libresign', 'You need to create an account to sign this file.'),
		}),
	}, {
		path: '/apps/libresign/#validation',
		component: () => import('../views/Validation'),
		name: 'validation',
	}, {
		path: '/apps/libresign/validation/:uuid',
		component: () => import('../views/Validation'),
		name: 'validationFile',
		props: (route) => ({
			uuid: route.params.uuid,
		}),
	}, {
		path: '/apps/libresign/sign/:uuid#error',
		component: () => import('../views/DefaultPageError'),
		name: 'DefaultPageError',
	}, {
		path: '/apps/libresign/sign/:uuid#success',
		component: () => import('../views/DefaultPageSuccess'),
		name: 'DefaultPageSuccess',
	}, {
		path: '/apps/libresign/#reset-password',
		component: () => import('../views/ResetPassword'),
		name: 'ResetPassword',
	}, {
		path: '/apps/libresign/#account',
		component: () => import('../views/Account'),
		name: 'Account',
	}, {
		path: '/apps/libresign/#create-password',
		component: () => import('../views/CreatePassword'),
		name: 'CreatePassword',
	},
]

export default routes
